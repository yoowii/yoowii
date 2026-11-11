<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Repository;

use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;

interface SupplierPricingMatrixVersionRepository
{
    /**
     * @param list<SupplierProduct> $supplierProducts
     *
     * @return list<SupplierPricingMatrixVersion>
     */
    public function findSelectableFor(
        array $supplierProducts,
        string $currencyCode,
        \DateTimeImmutable $at,
    ): array;
}
