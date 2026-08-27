<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Flyer;

use App\Yoowii\Pricing\Domain\Flyer\Exception\FlyerPriceNotFound;
use App\Yoowii\Pricing\Domain\Flyer\FlyerCalculator;
use App\Yoowii\Pricing\Domain\Flyer\FlyerConfiguration;
use App\Yoowii\Pricing\Domain\Flyer\FlyerPricingPolicy;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class FlyerCalculatorTest extends TestCase
{
    public function testItCalculatesTheCustomerPriceWithoutExposingSupplierCosts(): void
    {
        $product = self::supplierProduct('laboprint');
        $route = self::route($product, 10);
        $matrix = self::matrix($product, '2026-09-01', [
            'a5|two_sided|coated_gloss|135|1000|none' => self::entry(3100, 900),
        ]);

        $quote = (new FlyerCalculator(new FixedSupplierRouter()))->calculate(
            'PRINT_FLYER',
            self::configuration(),
            new FlyerPricingPolicy('retail-2026-09', 3500, 1200, 500),
            'EUR',
            new \DateTimeImmutable('2026-09-15T12:00:00+00:00'),
            [$route],
            [$matrix],
        );

        self::assertSame('laboprint', $quote->supplierCode());
        self::assertSame(4000, $quote->supplierCost());
        self::assertSame(1400, $quote->margin());
        self::assertSame(5900, $quote->pricingSnapshot()->unitPrice());
        self::assertSame(['total' => 5900], $quote->pricingSnapshot()->toArray()['price_breakdown']);
    }

    public function testItFallsBackWhenThePrimarySupplierCannotPriceTheConfiguration(): void
    {
        $primaryProduct = self::supplierProduct('laboprint');
        $fallbackProduct = self::supplierProduct('123imprim');
        $primaryMatrix = self::matrix($primaryProduct, '2026-09-01', [
            'a4|two_sided|coated_gloss|135|1000|none' => self::entry(5000, 900),
        ]);
        $fallbackMatrix = self::matrix($fallbackProduct, '2026-09-01', [
            'a5|two_sided|coated_gloss|135|1000|none' => self::entry(3200, 800),
        ]);

        $quote = (new FlyerCalculator(new FixedSupplierRouter()))->calculate(
            'PRINT_FLYER',
            self::configuration(),
            new FlyerPricingPolicy('retail-2026-09', 2500, 1000),
            'EUR',
            new \DateTimeImmutable('2026-09-15T12:00:00+00:00'),
            [self::route($primaryProduct, 10), self::route($fallbackProduct, 20)],
            [$primaryMatrix, $fallbackMatrix],
        );

        self::assertSame('123imprim', $quote->supplierCode());
        self::assertSame(5000, $quote->pricingSnapshot()->unitPrice());
    }

    public function testItUsesTheMostRecentEffectiveActiveMatrix(): void
    {
        $product = self::supplierProduct('laboprint');
        $oldMatrix = self::matrix($product, '2026-09-01', [
            'a5|two_sided|coated_gloss|135|1000|none' => self::entry(3000, 800),
        ], '2026-09-01');
        $newMatrix = self::matrix($product, '2026-09-10', [
            'a5|two_sided|coated_gloss|135|1000|none' => self::entry(4000, 800),
        ], '2026-09-10');

        $quote = (new FlyerCalculator(new FixedSupplierRouter()))->calculate(
            'PRINT_FLYER',
            self::configuration(),
            new FlyerPricingPolicy('retail-2026-09', 0, 1000),
            'EUR',
            new \DateTimeImmutable('2026-09-15T12:00:00+00:00'),
            [self::route($product, 10)],
            [$oldMatrix, $newMatrix],
        );

        self::assertSame('2026-09-10', $quote->matrixVersion());
        self::assertSame(5800, $quote->pricingSnapshot()->unitPrice());
    }

    public function testItFailsWhenNoActiveMatrixCanPriceTheConfiguration(): void
    {
        $product = self::supplierProduct('laboprint');
        $matrix = self::matrix($product, '2026-09-01', [
            'a4|two_sided|coated_gloss|135|1000|none' => self::entry(5000, 900),
        ]);

        $this->expectException(FlyerPriceNotFound::class);

        (new FlyerCalculator(new FixedSupplierRouter()))->calculate(
            'PRINT_FLYER',
            self::configuration(),
            new FlyerPricingPolicy('retail-2026-09', 3500, 1200),
            'EUR',
            new \DateTimeImmutable('2026-09-15T12:00:00+00:00'),
            [self::route($product, 10)],
            [$matrix],
        );
    }

    private static function configuration(): FlyerConfiguration
    {
        return new FlyerConfiguration('a5', 'two_sided', 'coated_gloss', 135, 1000, 'none');
    }

    private static function supplierProduct(string $supplierCode): SupplierProduct
    {
        return new SupplierProduct(
            new PrintSupplier($supplierCode, $supplierCode, SupplierIntegrationMode::Matrix),
            'FLYER_STANDARD',
            'Flyer standard',
        );
    }

    private static function route(SupplierProduct $product, int $priority): SupplierRoute
    {
        return new SupplierRoute(
            'PRINT_FLYER',
            $product,
            $priority,
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );
    }

    /**
     * @param array<string, array{configuration: array<string, mixed>, production_cost: int, shipping_cost: int}> $entries
     */
    private static function matrix(
        SupplierProduct $product,
        string $version,
        array $entries,
        string $effectiveFrom = '2026-09-01',
    ): SupplierPricingMatrixVersion {
        $matrix = new SupplierPricingMatrixVersion(
            $product,
            $version,
            'EUR',
            ['schema_version' => 1, 'calculator' => 'print.flyer', 'entries' => $entries],
            new \DateTimeImmutable(sprintf('%sT00:00:00+00:00', $effectiveFrom)),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        );
        $matrix->activate(new \DateTimeImmutable('2026-08-27T13:00:00+00:00'));

        return $matrix;
    }

    /** @return array{configuration: array<string, mixed>, production_cost: int, shipping_cost: int} */
    private static function entry(int $productionCost, int $shippingCost): array
    {
        return [
            'configuration' => self::configuration()->toArray(),
            'production_cost' => $productionCost,
            'shipping_cost' => $shippingCost,
        ];
    }
}
