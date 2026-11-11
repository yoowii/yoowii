<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\Application;

use App\Entity\Product\Product;

final readonly class MainNavigationTaxon
{
    /**
     * @param list<MainNavigationTaxonLink> $children
     * @param list<Product> $products
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $locale,
        public int $level,
        public bool $hasChildren,
        public bool $hasProducts,
        public bool $hasMoreProducts,
        public bool $hasPanel,
        public ?MainNavigationTaxonLink $parent,
        public array $children,
        public array $products,
    ) {
    }
}
