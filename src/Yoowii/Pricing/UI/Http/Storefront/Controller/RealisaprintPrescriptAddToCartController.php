<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\RealisaprintPrescriptQuoteCreator;
use App\Yoowii\Pricing\Application\RealisaprintResponseError;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\Application\StorefrontPrintSourcingResolver;
use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
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
        if (!is_scalar($quantity) || !ctype_digit((string) $quantity) || (int) $quantity < 1 || (int) $quantity > 1000000
            || !is_string($delivery) || !in_array($delivery, ['standard', 'urgence', 'express'], true)
            || !is_string($key) || !preg_match('/^[a-zA-Z0-9_-]{16,100}$/D', $key)) {
            return $this->failure('Quantité, délai ou identifiant de demande invalide.', 422);
        }
        $session = $request->getSession();
        $sessionKey = 'yoowii_prescript_cart_' . hash('sha256', $productCode . ':' . $key);
        $storedConfigurationCode = $session->get($sessionKey);
        if (is_string($storedConfigurationCode) && preg_match('/^\d+$/D', $storedConfigurationCode)) {
            return $this->configurationCodeResponse($storedConfigurationCode, Response::HTTP_OK);
        }

        $product = $productRepository->findOneByChannelAndCode($channelContext->getChannel(), $productCode);
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
        if (!is_array($provider) || '' === (string) ($provider['product'] ?? '') || '' === (string) ($provider['stock'] ?? '')) {
            return $this->failure('Configuration fournisseur invalide.', 422);
        }
        $raw = $input['variables'] ?? null;
        if (!is_array($raw) || count($raw) < 1 || count($raw) > 300) {
            return $this->failure('Variables fournisseur manquantes.', 422);
        }
        $variables = [];
        foreach ($raw as $name => $value) {
            if (!is_string($name) || !preg_match('/^VARTICLE_\d+_$/D', $name)
                || !is_scalar($value) || strlen((string) $value) > 500) {
                return $this->failure('Variable fournisseur invalide.', 422);
            }
            if ('' !== trim((string) $value)) {
                $variables[$name] = (string) $value;
            }
        }

        // The supplier script already synchronizes quantity and delivery in the Prescript form.
        // Validate these technical fields when their names are configured in the active mapping.
        $diagnostic = $provider['prescript_diagnostic_variables'] ?? [];
        if (is_array($diagnostic)) {
            $quantityVariable = $diagnostic['quantity'] ?? null;
            $deliveryVariable = $diagnostic['delivery'] ?? null;
            if (is_string($quantityVariable) && isset($variables[$quantityVariable])
                && $variables[$quantityVariable] !== (string) $quantity) {
                return $this->failure('La quantité fournisseur ne correspond pas à votre sélection.', 422);
            }
            if (is_string($deliveryVariable) && isset($variables[$deliveryVariable])) {
                $deliveryValues = $provider['prescript_delivery_values'] ?? ['standard' => '0', 'urgence' => '1', 'express' => '2'];
                if (!is_array($deliveryValues) || (string) ($deliveryValues[$delivery] ?? '') !== $variables[$deliveryVariable]) {
                    return $this->failure('Le délai fournisseur ne correspond pas à votre sélection.', 422);
                }
            }
        }

        $cart = $cartContext->getCart();
        if (!$cart instanceof \Sylius\Component\Core\Model\OrderInterface) {
            return $this->failure('Panier indisponible.', 500);
        }
        foreach ($cart->getItems() as $item) {
            $existing = $item->getVariant()?->getProduct();
            if (!$existing instanceof Product || FulfillmentType::Print !== $existing->getFulfillmentType()) {
                return $this->failure('Finalisez ou videz votre panier avant d’ajouter un produit imprimé.', 409);
            }
        }

        try {
            $saved = $client->post('save_configuration', [
                'product' => (string) $provider['product'],
                'stock' => (string) $provider['stock'],
                'variables' => $variables,
                'code_only' => 1,
            ]);
            $code = $saved['code'] ?? null;
            $status = $saved['_http_status'] ?? 200;
            if (!is_int($status) || $status < 200 || $status >= 300 || RealisaprintResponseError::isPresent($saved)
                || !is_scalar($code) || !preg_match('/^\d+$/D', (string) $code)) {
                $logger->warning('Realisaprint Prescript save_configuration was rejected.', [
                    'product_code' => $productCode,
                    'mapping_id' => $mapping->id(),
                    'mapping_version' => $mapping->version(),
                    'provider_product' => (string) $provider['product'],
                    'provider_stock' => (string) $provider['stock'],
                    'http_status' => $status,
                    'response_keys' => array_keys($saved),
                    'supplier_error_present' => RealisaprintResponseError::isPresent($saved),
                    'configuration_code_present' => is_scalar($code) && '' !== (string) $code,
                ]);

                return $this->failure('Realisaprint a refusé la configuration.', 502);
            }
            $savedDelivery = $saved['delai'] ?? null;
            if (is_string($savedDelivery) && mb_strtolower(trim($savedDelivery)) !== $delivery) {
                return $this->failure('Le délai enregistré par Realisaprint ne correspond pas à votre sélection.', 422);
            }
            $savedQuantity = $saved['current_values'][$diagnostic['quantity'] ?? ''] ?? null;
            if (null !== $savedQuantity && (string) $savedQuantity !== (string) $quantity) {
                return $this->failure('La quantité enregistrée chez Realisaprint est incorrecte.', 422);
            }
            $snapshot = $creator->create($definitionCode, $mapping, (string) $code, (int) $quantity, $pricingPolicy->get(), new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $delivery, $sourcing->route());
            if (!$cart->isEmpty() && null !== $cart->getCurrencyCode() && $cart->getCurrencyCode() !== $snapshot->currencyCode()) {
                return $this->failure('La devise du panier ne correspond pas au devis.', 409);
            }
            if ($cart->isEmpty() || null === $cart->getCurrencyCode()) {
                $cart->setCurrencyCode($snapshot->currencyCode());
            }
            $cartItem = $cartItemFactory->createNew();
            if (!$cartItem instanceof OrderItem) {
                throw new \LogicException('La fabrique panier doit produire des OrderItem Yoowii.');
            }
            $cartItem->setVariant($variant);
            $quantityModifier->modify($cartItem, 1);
            $cartItem->replacePricingSnapshot($snapshot);
            $orderModifier->addToOrder($cart, $cartItem);
            $entityManager->persist($cart);
            $entityManager->flush();
            $session->set($sessionKey, (string) $code);

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

    private function failure(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['success' => false, 'message' => $message], $status, ['Cache-Control' => 'no-store']);
    }

    private function configurationCodeResponse(string $code, int $status): Response
    {
        return new Response($code, $status, ['Cache-Control' => 'no-store', 'Content-Type' => 'text/plain; charset=utf-8']);
    }
}
