<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application\Import;

use App\Yoowii\Sourcing\Application\Import\Exception\PricingMatrixCsvImportFailed;
use App\Yoowii\Sourcing\Application\Import\FlyerPricingMatrixCsvImporter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class FlyerPricingMatrixCsvImporterTest extends TestCase
{
    public function testItImportsASemicolonSeparatedMatrixAtomically(): void
    {
        $csv = <<<'CSV'
format;sides;paper;grammage;quantity;finishing;production_cost;shipping_cost
a5;two_sided;coated_gloss;135;500;none;2100;800
a5;two_sided;coated_gloss;135;1000;none;3100;900
CSV;

        $result = (new FlyerPricingMatrixCsvImporter())->import(
            self::supplierProduct(),
            '2026-09-01',
            'EUR',
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
            $csv,
        );

        self::assertSame(2, $result->importedRows());
        self::assertSame(PricingMatrixStatus::Draft, $result->matrix()->status());
        self::assertSame(
            3100,
            $result->matrix()->matrix()['entries']['a5|two_sided|coated_gloss|135|1000|none']['production_cost'],
        );
    }

    public function testItAcceptsACommaSeparatedUtf8BomDocument(): void
    {
        $csv = "\xEF\xBB\xBFformat,sides,paper,grammage,quantity,finishing,production_cost,shipping_cost\n"
            . "a5,two_sided,recycled,170,1000,none,3500,900\n";

        $result = (new FlyerPricingMatrixCsvImporter())->import(
            self::supplierProduct(),
            '2026-09-01',
            'EUR',
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
            $csv,
        );

        self::assertSame(1, $result->importedRows());
    }

    public function testItReportsDuplicateAndInvalidRowsWithoutCreatingAMatrix(): void
    {
        $csv = <<<'CSV'
format;sides;paper;grammage;quantity;finishing;production_cost;shipping_cost
a5;two_sided;coated_gloss;135;1000;none;3100;900
a5;two_sided;coated_gloss;135;1000;none;3200;900
a5;invalid;coated_gloss;135;500;none;not-a-price;800
CSV;

        try {
            (new FlyerPricingMatrixCsvImporter())->import(
                self::supplierProduct(),
                '2026-09-01',
                'EUR',
                new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
                new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
                $csv,
            );
            self::fail('The import should have failed.');
        } catch (PricingMatrixCsvImportFailed $exception) {
            self::assertCount(2, $exception->errors());
            self::assertStringContainsString('Line 3: duplicate configuration', $exception->errors()[0]);
            self::assertStringContainsString('Line 4: Flyer sides must be', $exception->errors()[1]);
        }
    }

    public function testItRejectsMissingColumns(): void
    {
        $this->expectException(PricingMatrixCsvImportFailed::class);

        (new FlyerPricingMatrixCsvImporter())->import(
            self::supplierProduct(),
            '2026-09-01',
            'EUR',
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
            "format;sides\na5;two_sided\n",
        );
    }

    private static function supplierProduct(): SupplierProduct
    {
        return new SupplierProduct(
            new PrintSupplier('laboprint', 'Laboprint', SupplierIntegrationMode::Matrix),
            'FLYER_STANDARD',
            'Flyer standard',
        );
    }
}
