<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Validation;

use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;

final class PricingMatrixActivationPolicy
{
    /** @param iterable<SupplierPricingMatrixVersion> $matrices */
    public function hasConflict(
        iterable $matrices,
        SupplierPricingMatrixVersion $matrix,
    ): bool {
        $productCode = $matrix->matrix()['product_code'] ?? null;

        foreach ($matrices as $candidate) {
            if ($candidate === $matrix) {
                continue;
            }

            if (null !== $matrix->id() && $candidate->id() === $matrix->id()) {
                continue;
            }

            if (
                PricingMatrixStatus::Active === $candidate->status()
                && $candidate->supplierProduct() === $matrix->supplierProduct()
                && $candidate->currencyCode() === $matrix->currencyCode()
                && $candidate->effectiveFrom() == $matrix->effectiveFrom()
                && ($candidate->matrix()['product_code'] ?? null) === $productCode
            ) {
                return true;
            }
        }

        return false;
    }
}
