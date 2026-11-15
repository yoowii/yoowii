<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\PrescriptCartOperationReservation;
use App\Yoowii\Pricing\Application\RealisaprintPrescriptQuoteCreator;
use App\Yoowii\Pricing\Application\RealisaprintResponseError;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\Application\StorefrontPrintSourcingResolver;
use App\Yoowii\Pricing\Domain\Model\PrescriptCartOperation;
use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface as CoreOrderItemInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** One browser request; supplier save, authoritative quote and cart insertion remain separate server operations. */
final class RealisaprintPrescriptAddToCartController extends AbstractController
{
    /** @param ProductRepositoryInterface<Product> $productRepository
     *  @param CartItemFactoryInterface<CoreOrderItemInterface> $cartItemFactory
     */
    #[Route('/products/{productCode}/prescript/add-to-cart', name: 'yoowii_shop_realisaprint_prescript_add_to_cart', requirements: ['productCode' => '[A-Za-z0-9._-]+'], methods: ['POST'])]
    public function __invoke(
        string $productCode,
        Request $request,
        ProductRepositoryInterface $productRepository,
        ChannelContextInterface $channelContext,
        EntityManagerInterface $entityManager,
        StorefrontPrintSourcingResolver $sourcingResolver,
        RealisaprintClient $client,
        RealisaprintPrescriptQuoteCreator $creator,
        PrescriptCartOperationReservation $operationReservation,
        RetailPrintPricingPolicyProvider $pricingPolicy,
        CartContextInterface $cartContext,
        CartItemFactoryInterface $cartItemFactory,
        OrderItemQuantityModifierInterface $quantityModifier,
        OrderModifierInterface $orderModifier,
        CsrfTokenManagerInterface $csrf,
        LoggerInterface $logger,
    ): Response {
        try {
            $input = $request->toArray();
        } catch (\Throwable) {
            return $this->failure('Requête invalide.', 400);
        }
        if (!$csrf->isTokenValid(new CsrfToken('prescript_quote_' . $productCode, is_string($input['_token'] ?? null) ? $input['_token'] : ''))) {
            return $this->failure('Jeton de sécurité invalide.', 403);
        }
        $quantity = $input['quantity'] ?? null;
        $delivery = $input['delivery'] ?? null;
        $key = $input['idempotency_key'] ?? null;
        if (!is_scalar($quantity) || !ctype_digit((string) $quantity) || (int) $quantity < 1 || (int) $quantity > 1000000 ||
            !is_string($delivery) || !in_array($delivery, ['standard', 'urgence', 'express'], true) ||
            !is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,100}$/D', $key)) {
            return $this->failure('Quantité, délai ou identifiant de demande invalide.', 422);
        }
        $channel = $channelContext->getChannel();
        if (!$channel instanceof \Sylius\Component\Core\Model\ChannelInterface) {
            return $this->failure('Canal de vente indisponible.', 500);
        }
        $product = $productRepository->findOneByChannelAndCode($channel, $productCode);
        if (!$product instanceof Product || !$product->isEnabled() || FulfillmentType::Print !== $product->getFulfillmentType()) {
            return $this->failure('Produit indisponible.', 404);
        }
        $variant = $product->getEnabledVariants()->first();
        if (!$variant instanceof ProductVariant || !$variant->isEnabled() || null === $variant->getCode()) {
            return $this->failure('Variante indisponible.', 422);
        }
        $sourcing = $sourcingResolver->resolve($productCode, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $mapping = $sourcing->mapping();
        if (ConfiguratorKind::Prescript !== $sourcing->configuratorKind() || !$mapping instanceof SupplierProductMappingVersion) {
            return $this->failure('Mapping fournisseur indisponible.', 422);
        }
        $definitionCode = $mapping->printProductDefinition()?->productCode() ?? $product->getPrintDefinitionCode();
        if (!is_string($definitionCode) || '' === $definitionCode) {
            return $this->failure('Définition de configurateur indisponible.', 422);
        }
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        $providerProduct = is_array($provider) ? ($provider['product'] ?? null) : null;
        $providerStock = is_array($provider) ? ($provider['stock'] ?? null) : null;
        if (!is_array($provider) || !is_string($providerProduct) || '' === trim($providerProduct) || !is_string($providerStock) || '' === trim($providerStock)) {
            return $this->failure('Configuration fournisseur invalide.', 422);
        }
        $raw = $input['variables'] ?? null;
        if (!is_array($raw) || count($raw) < 1 || count($raw) > 300) {
            return $this->failure('Variables fournisseur manquantes.', 422);
        }
        $variables = [];
        foreach ($raw as $name => $value) {
            if (!is_string($name) || !preg_match('/^VARTICLE_\d+_$/D', $name) ||
                !is_scalar($value) || strlen((string) $value) > 500) {
                return $this->failure('Variable fournisseur invalide.', 422);
            }
            if ('' !== trim((string) $value)) {
                $variables[$name] = (string) $value;
            }
        }

        // The supplier script already synchronizes quantity and delivery in the Prescript form.
        // Validate these technical fields when their names are configured in the active mapping.
        $diagnostic = $provider['prescript_diagnostic_variables'] ?? [];
        $quantityVariable = is_array($diagnostic) && is_string($diagnostic['quantity'] ?? null) ? $diagnostic['quantity'] : null;
        $deliveryVariable = is_array($diagnostic) && is_string($diagnostic['delivery'] ?? null) ? $diagnostic['delivery'] : null;
        if (is_array($diagnostic)) {
            if (null !== $quantityVariable && isset($variables[$quantityVariable]) &&
                $variables[$quantityVariable] !== (string) $quantity) {
                return $this->failure('La quantité fournisseur ne correspond pas à votre sélection.', 422);
            }
            if (null !== $deliveryVariable && isset($variables[$deliveryVariable])) {
                $deliveryValues = $provider['prescript_delivery_values'] ?? ['standard' => '0', 'urgence' => '1', 'express' => '2'];
                $expectedDeliveryValue = is_array($deliveryValues) ? ($deliveryValues[$delivery] ?? null) : null;
                if (!is_scalar($expectedDeliveryValue) || (string) $expectedDeliveryValue !== $variables[$deliveryVariable]) {
                    return $this->failure('Le délai fournisseur ne correspond pas à votre sélection.', 422);
                }
            }
        }

        $cart = $cartContext->getCart();
        if (!$cart instanceof Order) {
            return $this->failure('Panier indisponible.', 500);
        }
        foreach ($cart->getItems() as $item) {
            $existing = $item instanceof OrderItem ? $item->getVariant()?->getProduct() : null;
            if (!$existing instanceof Product || FulfillmentType::Print !== $existing->getFulfillmentType()) {
                return $this->failure('Finalisez ou videz votre panier avant d’ajouter un produit imprimé.', 409);
            }
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $fingerprint = hash('sha256', json_encode($this->canonicalize([
            'product' => $productCode, 'variant' => $variant->getCode(), 'quantity' => (int) $quantity, 'delivery' => $delivery,
            'variables' => $variables, 'mapping_version' => $mapping->version(), 'provider_product' => $providerProduct, 'provider_stock' => $providerStock,
        ]), \JSON_THROW_ON_ERROR));
        $operation = $entityManager->getRepository(PrescriptCartOperation::class)->findOneBy(['idempotencyKeyHash' => hash('sha256', $key)]);
        if ($operation instanceof PrescriptCartOperation) {
            if ($operation->cart() !== $cart || !hash_equals($operation->payloadFingerprint(), $fingerprint)) {
                return $this->failure('Cette clé d’idempotence appartient à une autre demande.', 409);
            }
            $item = $operation->orderItem();
            if ('completed' === $operation->status() && $item instanceof OrderItem && $item->getOrder() === $cart && $cart->getItems()->contains($item) && is_string($operation->configurationCode())) {
                return $this->configurationCodeResponse($operation->configurationCode(), Response::HTTP_OK);
            }
            if ('completed' === $operation->status()) {
                return $this->failure('La ligne panier liée à cette demande n’existe plus.', 409);
            }
        } else {
            // Persist the cart and the operation before save_configuration: a local failure remains resumable.
            $entityManager->persist($cart);
            $entityManager->flush();
            $operation = new PrescriptCartOperation(hash('sha256', $key), $fingerprint, $mapping->version(), $cart, $product, $variant, $now, $now);
            $entityManager->persist($operation);

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                return $this->failure('Cette demande est déjà en cours de traitement.', 409);
            }
        }

        $attemptToken = $operationReservation->reserve($operation, $now);
        if (null === $attemptToken) {
            return $this->failure(
                'pending_confirmation' === $operation->status()
                    ? 'La configuration fournisseur doit être confirmée manuellement avant toute reprise.'
                    : 'Cette demande est déjà en cours de traitement.',
                409,
            );
        }

        try {
            $code = $operation->configurationCode();
            $saved = [];
            if (null === $code) {
                $saved = $client->post('save_configuration', [
                    'product' => $providerProduct,
                    'stock' => $providerStock,
                    'variables' => $variables,
                ]);
                $code = $saved['code'] ?? null;
            }
            $status = $saved['_http_status'] ?? 200;
            if (!is_int($status) || $status < 200 || $status >= 300 || RealisaprintResponseError::isPresent($saved) ||
                !is_scalar($code) || !preg_match('/^\d+$/D', (string) $code)) {
                $logger->warning('Realisaprint Prescript save_configuration was rejected.', [
                    'product_code' => $productCode,
                    'mapping_id' => $mapping->id(),
                    'mapping_version' => $mapping->version(),
                    'provider_product' => $providerProduct,
                    'provider_stock' => $providerStock,
                    'http_status' => $status,
                    'response_keys' => array_keys($saved),
                    'supplier_error_present' => RealisaprintResponseError::isPresent($saved),
                    'configuration_code_present' => is_scalar($code) && '' !== (string) $code,
                ]);

                return $this->failure('Cette configuration ne peut pas être commandée automatiquement. Demandez un devis personnalisé.', 422, true);
            }
            $savedDelivery = $saved['delai'] ?? null;
            if (is_string($savedDelivery) && mb_strtolower(trim($savedDelivery)) !== $delivery) {
                return $this->failure('Le délai enregistré par Realisaprint ne correspond pas à votre sélection.', 422);
            }
            $savedValues = $saved['current_values'] ?? null;
            $savedQuantity = is_array($savedValues) && null !== $quantityVariable ? ($savedValues[$quantityVariable] ?? null) : null;
            if (null !== $savedQuantity && (!is_scalar($savedQuantity) || (string) $savedQuantity !== (string) $quantity)) {
                return $this->failure('La quantité enregistrée chez Realisaprint est incorrecte.', 422);
            }
            if (null === $operation->configurationCode()) {
                if (!$operationReservation->recordConfiguration($operation, $attemptToken, (string) $code, $now)) {
                    return $this->failure('La réservation de cette demande a expiré avant l’enregistrement fournisseur.', 409);
                }
            }
            $snapshot = $creator->create($definitionCode, $mapping, (string) $code, (int) $quantity, $pricingPolicy->get(), $now, $delivery, $sourcing->route());
            if (!$cart->isEmpty() && null !== $cart->getCurrencyCode() && $cart->getCurrencyCode() !== $snapshot->currencyCode()) {
                return $this->failure('La devise du panier ne correspond pas au devis.', 409);
            }
            if ($cart->isEmpty() || null === $cart->getCurrencyCode()) {
                $cart->setCurrencyCode($snapshot->currencyCode());
            }
            $completed = $operationReservation->finalize($operation, $attemptToken, function () use ($cartItemFactory, $variant, $quantityModifier, $snapshot, $orderModifier, $cart, $operation, $now, $entityManager): void {
                $cartItem = $cartItemFactory->createNew();
                if (!$cartItem instanceof OrderItem) {
                    throw new \LogicException('La fabrique panier doit produire des OrderItem Yoowii.');
                }
                $cartItem->setVariant($variant);
                $quantityModifier->modify($cartItem, 1);
                $cartItem->replacePricingSnapshot($snapshot);
                $orderModifier->addToOrder($cart, $cartItem);
                $operation->complete($cartItem, $now);
                $entityManager->persist($cart);
            });
            if (!$completed) {
                return $this->failure('Cette demande a été reprise par un autre traitement.', 409);
            }

            return $this->configurationCodeResponse((string) $code, Response::HTTP_CREATED);
        } catch (\Throwable $exception) {
            $logger->error('Realisaprint Prescript add-to-cart failed after configuration submission.', [
                'product_code' => $productCode,
                'mapping_id' => $mapping->id(),
                'mapping_version' => $mapping->version(),
                'failure_class' => $exception::class,
            ]);

            return $this->failure('Impossible de finaliser la configuration ou le panier. Veuillez réessayer.', 502);
        }
    }

    private function failure(string $message, int $status, bool $requestQuote = false): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'request_quote' => $requestQuote,
        ], $status, ['Cache-Control' => 'no-store']);
    }

    private function configurationCodeResponse(string $code, int $status): Response
    {
        return new Response($code, $status, ['Cache-Control' => 'no-store', 'Content-Type' => 'text/plain; charset=utf-8']);
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private function canonicalize(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
