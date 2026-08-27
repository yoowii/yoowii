<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Import;

use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;

final readonly class PrintPricingMatrixImportResult
{
    public function __construct(
        private SupplierPricingMatrixVersion $matrix,
        private int $importedRows,
    ) {
    }

    public function matrix(): SupplierPricingMatrixVersion
    {
        return $this->matrix;
    }

    public function importedRows(): int
    {
        return $this->importedRows;
    }
}
