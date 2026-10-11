<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Entity\Product\Product;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;

final class StorefrontProductSearch
{
    /** @param ProductRepositoryInterface<Product> $productRepository */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    /** @return list<Product> */
    public function find(string $query, int $limit = 12): array
    {
        $needle = mb_strtolower(trim($query));

        if ('' === $needle) {
            return [];
        }

        $products = [];
        foreach ($this->productRepository->findBy(['enabled' => true]) as $product) {
            if (!$product instanceof Product || !$product->hasChannel($this->channelContext->getChannel())) {
                continue;
            }

            if (!str_contains(mb_strtolower(($product->getName() ?? '') . ' ' . $product->getCode()), $needle)) {
                continue;
            }

            $products[] = $product;
            if (count($products) >= $limit) {
                break;
            }
        }

        return $products;
    }
}
