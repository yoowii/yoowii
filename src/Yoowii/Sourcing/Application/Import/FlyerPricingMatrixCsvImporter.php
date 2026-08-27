<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Import;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;

/**
 * Compatibility facade for the lot 2 flyer import command.
 *
 * New print products must use PrintPricingMatrixCsvImporter with their definition.
 */
final readonly class FlyerPricingMatrixCsvImporter
{
    public function __construct(private PrintPricingMatrixCsvImporter $importer = new PrintPricingMatrixCsvImporter())
    {
    }

    public function import(
        SupplierProduct $supplierProduct,
        string $version,
        string $currencyCode,
        \DateTimeImmutable $effectiveFrom,
        \DateTimeImmutable $importedAt,
        string $csv,
    ): FlyerPricingMatrixImportResult {
        $result = $this->importer->import(
            BuiltInPrintProductDefinitions::flyer(),
            $supplierProduct,
            $version,
            $currencyCode,
            $effectiveFrom,
            $importedAt,
            $csv,
        );

        return new FlyerPricingMatrixImportResult($result->matrix(), $result->importedRows());
    }
}
