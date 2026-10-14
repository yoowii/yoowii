<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\Application;

final readonly class MainNavigationTaxonLink
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $locale,
    ) {
    }
}
