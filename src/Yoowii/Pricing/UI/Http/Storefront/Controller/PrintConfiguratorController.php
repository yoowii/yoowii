<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Keeps historical /print/... links working while Sylius product pages remain canonical.
 */
final class PrintConfiguratorController extends AbstractController
{
    /** @param ProductRepositoryInterface<Product> $productRepository */
    #[Route('/print/{productCode}', name: 'yoowii_shop_print_configure', requirements: ['productCode' => '[A-Za-z0-9._-]+'], methods: ['GET'])]
    public function __invoke(
        string $productCode,
        Request $request,
        ProductRepositoryInterface $productRepository,
        ChannelContextInterface $channelContext,
    ): Response {
        $product = $productRepository->findOneBy(['code' => $productCode]);

        if (!$product instanceof Product) {
            $product = $productRepository->findOneBy(['printDefinitionCode' => $productCode]);
        }

        if (
            !$product instanceof Product
            || !$product->isEnabled()
            || !$product->hasChannel($channelContext->getChannel())
            || FulfillmentType::Print !== $product->getFulfillmentType()
        ) {
            throw $this->createNotFoundException('Le produit print demandé n’est pas disponible.');
        }

        return $this->redirectToRoute('sylius_shop_product_show', [
            'slug' => $product->getSlug(),
            '_locale' => $request->getLocale(),
        ]);
    }
}
