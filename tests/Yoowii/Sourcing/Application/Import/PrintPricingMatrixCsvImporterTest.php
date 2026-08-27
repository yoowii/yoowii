<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application\Import;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Sourcing\Application\Import\Exception\PricingMatrixCsvImportFailed;
use App\Yoowii\Sourcing\Application\Import\PrintPricingMatrixCsvImporter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class PrintPricingMatrixCsvImporterTest extends TestCase
{
    public function testHeadersAreDrivenByTheProductDefinition(): void
    {
        $csv = <<<'CSV'
format;sides;paper;grammage;quantity;finishing;corners;production_cost;shipping_cost
85x55;two_sided;coated_matt;350;500;none;square;2400;700
CSV;

        $result = (new PrintPricingMatrixCsvImporter())->import(
            BuiltInPrintProductDefinitions::businessCard(),
            self::supplierProduct(),
            '2026-09-01',
            'EUR',
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
            $csv,
        );
        $payload = $result->matrix()->matrix();

        self::assertSame('print.matrix_exact', $payload['calculator']);
        self::assertSame('PRINT_BUSINESS_CARD', $payload['product_code']);
        self::assertSame('1', $payload['product_schema_version']);

        /** @var list<string> $pricingAxes */
        $pricingAxes = $payload['pricing_axes'];
        self::assertContains('corners', $pricingAxes);
    }

    public function testItRejectsAColumnFromAnotherProductSchema(): void
    {
        $csv = <<<'CSV'
format;sides;paper;grammage;quantity;finishing;corners;production_cost;shipping_cost
a5;two_sided;coated_gloss;135;1000;none;square;3100;900
CSV;

        try {
            (new PrintPricingMatrixCsvImporter())->import(
                BuiltInPrintProductDefinitions::flyer(),
                self::supplierProduct(),
                '2026-09-01',
                'EUR',
                new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
                new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
                $csv,
            );
            self::fail('The foreign schema column should have been rejected.');
        } catch (PricingMatrixCsvImportFailed $exception) {
            self::assertContains('The column "corners" is not supported.', $exception->errors());
        }
    }

    private static function supplierProduct(): SupplierProduct
    {
        return new SupplierProduct(
            new PrintSupplier('realisaprint', 'Realisaprint', SupplierIntegrationMode::Matrix),
            'BUSINESS_CARD_STANDARD',
            'Business card standard',
        );
    }
}
