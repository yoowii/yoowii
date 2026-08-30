<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintCatalogController extends AbstractController
{
    /** @param ProductRepositoryInterface<Product> $productRepository */
    #[Route('/print', name: 'yoowii_shop_print_catalog', methods: ['GET'])]
    public function __invoke(
        ProductRepositoryInterface $productRepository,
        ChannelContextInterface $channelContext,
    ): Response {
        $products = array_values(array_filter(
            $productRepository->findBy([
                'enabled' => true,
                'fulfillmentType' => FulfillmentType::Print,
            ]),
            static fn (Product $product): bool => $product->hasChannel($channelContext->getChannel()),
        ));

        return $this->render('shop/print/index.html.twig', [
            'products' => $products,
        ]);
    }
}
