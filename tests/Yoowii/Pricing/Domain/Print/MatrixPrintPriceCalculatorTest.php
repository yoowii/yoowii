<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Print;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Pricing\Domain\Print\MatrixPrintPriceCalculator;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Sourcing\Application\Import\PrintPricingMatrixCsvImporter;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class MatrixPrintPriceCalculatorTest extends TestCase
{
    public function testTheSameEngineImportsAndPricesABusinessCard(): void
    {
        $definition = BuiltInPrintProductDefinitions::businessCard();
        $supplierProduct = new SupplierProduct(
            new PrintSupplier('realisaprint', 'Realisaprint', SupplierIntegrationMode::Matrix),
            'BUSINESS_CARD_STANDARD',
            'Business card standard',
        );
        $csv = <<<'CSV'
format;sides;paper;grammage;quantity;finishing;corners;production_cost;shipping_cost
85x55;two_sided;coated_matt;350;500;matte_lamination;rounded;2600;700
CSV;
        $import = (new PrintPricingMatrixCsvImporter())->import(
            $definition,
            $supplierProduct,
            '2026-09-01',
            'EUR',
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
            $csv,
        );
        $import->matrix()->activate(new \DateTimeImmutable('2026-08-27T13:00:00+00:00'));
        $configuration = $definition->configure([
            'format' => '85x55',
            'sides' => 'two_sided',
            'paper' => 'coated_matt',
            'grammage' => 350,
            'quantity' => 500,
            'finishing' => 'matte_lamination',
            'corners' => 'rounded',
        ]);
        $route = new SupplierRoute(
            'PRINT_BUSINESS_CARD',
            $supplierProduct,
            10,
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );

        $quote = (new MatrixPrintPriceCalculator(new FixedSupplierRouter()))->calculate(
            $configuration,
            new PrintPricingPolicy('retail-2026-09', 3000, 1000, 200),
            'EUR',
            new \DateTimeImmutable('2026-09-15T12:00:00+00:00'),
            [$route],
            [$import->matrix()],
        );

        self::assertSame('realisaprint', $quote->supplierCode());
        self::assertSame(3300, $quote->supplierCost());
        self::assertSame(4500, $quote->pricingSnapshot()->unitPrice());
        self::assertSame('print.matrix_exact', $quote->pricingSnapshot()->toArray()['calculator']);
        self::assertSame(
            'PRINT_BUSINESS_CARD',
            $quote->pricingSnapshot()->toArray()['configuration']['product_code'],
        );
    }
}
