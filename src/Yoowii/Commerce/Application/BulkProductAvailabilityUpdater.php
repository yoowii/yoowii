<?php

declare(strict_types=1);

namespace App\Yoowii\Commerce\Application;

use App\Entity\Product\Product;

final class BulkProductAvailabilityUpdater
{
    /**
     * @param iterable<Product> $products
     */
    public function update(iterable $products, bool $enabled): int
    {
        $updated = 0;

        foreach ($products as $product) {
            if ($product->isEnabled() === $enabled) {
                continue;
            }

            $product->setEnabled($enabled);
            ++$updated;
        }

        return $updated;
    }
}
