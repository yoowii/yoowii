<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\Quote\PrintQuoteStore;
use App\Yoowii\Pricing\Application\RealisaprintPrescriptQuoteCreator;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RealisaprintPrescriptQuoteController extends AbstractController
{
    /** @param ProductRepositoryInterface<Product> $productRepository */
    #[Route('/products/{productCode}/prescript/quote', name: 'yoowii_shop_realisaprint_prescript_quote', requirements: ['productCode' => '[A-Za-z0-9._-]+'], methods: ['POST'])]
    public function quote(string $productCode, Request $request, ProductRepositoryInterface $productRepository, ChannelContextInterface $channelContext, EntityManagerInterface $entityManager, RealisaprintPrescriptQuoteCreator $creator, RetailPrintPricingPolicyProvider $pricingPolicy, PrintQuoteStore $quoteStore, CsrfTokenManagerInterface $csrf): Response
    {
        $payload = $request->toArray();
        if (!$csrf->isTokenValid(new CsrfToken('prescript_quote_' . $productCode, is_string($payload['_token'] ?? null) ? $payload['_token'] : ''))) {
            return new JsonResponse(['message' => 'Jeton de sécurité invalide.'], Response::HTTP_FORBIDDEN, ['Cache-Control' => 'no-store']);
        }
        $product = $productRepository->findOneByChannelAndCode($channelContext->getChannel(), $productCode);
        if (!$product instanceof Product || !$product->isEnabled() || FulfillmentType::Print !== $product->getFulfillmentType()) {
            throw $this->createNotFoundException();
        }
        $code = $payload['code'] ?? null;
        $quantity = $payload['quantity'] ?? null;
        if (!is_scalar($code) || !is_scalar($quantity) || !ctype_digit((string) $quantity) || (int) $quantity < 1) {
            return new JsonResponse(['message' => 'La quantité sélectionnée est invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        }
        $mapping = $this->mapping($productCode, $entityManager);
        if (!$mapping instanceof SupplierProductMappingVersion) {
            return new JsonResponse(['message' => 'La configuration Préscript est indisponible.'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        }
        $variant = $product->getEnabledVariants()->first();
        if (!$variant instanceof ProductVariant || null === $variant->getCode()) {
            return new JsonResponse(['message' => 'La variante commerciale est indisponible.'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        }
        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $snapshot = $creator->create($productCode, $mapping, (string) $code, (int) $quantity, $pricingPolicy->get(), $now);
            $token = $quoteStore->issue($variant->getCode(), $productCode, $snapshot, $now);
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Le prix Préscript ne peut pas être calculé pour le moment.'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        }

        return new JsonResponse(['quote_token' => $token, 'quote_html' => $this->renderView('shop/product/show/_print_quote.html.twig', ['pricing_snapshot' => $snapshot, 'quote_token' => $token])], Response::HTTP_CREATED, ['Cache-Control' => 'no-store']);
    }

    private function mapping(string $productCode, EntityManagerInterface $entityManager): ?SupplierProductMappingVersion
    {
        foreach ($entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $productCode, 'active' => true]) as $mapping) {
            $provider = $mapping instanceof SupplierProductMappingVersion ? ($mapping->configurationMapping()['realisaprint'] ?? null) : null;
            if ($mapping instanceof SupplierProductMappingVersion && 'realisaprint' === $mapping->supplierProduct()->supplier()->code() && is_array($provider) && 'prescript' === ($provider['configurator'] ?? 'classic')) {
                return $mapping;
            }
        }

        return null;
    }
}
