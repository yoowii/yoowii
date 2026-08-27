<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application\Validation;

use App\Yoowii\Sourcing\Application\Validation\PricingMatrixActivationPolicy;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class PricingMatrixActivationPolicyTest extends TestCase
{
    public function testItRejectsTwoActiveMatricesForTheSameReferenceCurrencyAndDate(): void
    {
        $product = self::supplierProduct();
        $active = self::matrix($product, 'v1', 'EUR', 'PRINT_FLYER');
        $active->activate(new \DateTimeImmutable('2026-08-02T00:00:00+00:00'));
        $candidate = self::matrix($product, 'v2', 'EUR', 'PRINT_FLYER');

        self::assertTrue((new PricingMatrixActivationPolicy())->hasConflict([$active], $candidate));
    }

    public function testItIgnoresDraftsAndOtherCurrencies(): void
    {
        $product = self::supplierProduct();
        $draft = self::matrix($product, 'v1', 'EUR', 'PRINT_FLYER');
        $otherCurrency = self::matrix($product, 'v2', 'USD', 'PRINT_FLYER');
        $otherCurrency->activate(new \DateTimeImmutable('2026-08-02T00:00:00+00:00'));
        $otherProduct = self::matrix($product, 'v3', 'EUR', 'PRINT_BUSINESS_CARD');
        $otherProduct->activate(new \DateTimeImmutable('2026-08-02T00:00:00+00:00'));
        $candidate = self::matrix($product, 'v4', 'EUR', 'PRINT_FLYER');

        self::assertFalse((new PricingMatrixActivationPolicy())->hasConflict(
            [$draft, $otherCurrency, $otherProduct],
            $candidate,
        ));
    }

    private static function supplierProduct(): SupplierProduct
    {
        return new SupplierProduct(
            new PrintSupplier('laboprint', 'Laboprint', SupplierIntegrationMode::Matrix),
            'FLYER_STANDARD',
            'Flyer standard',
        );
    }

    private static function matrix(
        SupplierProduct $product,
        string $version,
        string $currency,
        string $productCode,
    ): SupplierPricingMatrixVersion {
        return new SupplierPricingMatrixVersion(
            $product,
            $version,
            $currency,
            [
                'product_code' => $productCode,
                'entries' => ['key' => ['production_cost' => 1000, 'shipping_cost' => 500]],
            ],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-01T00:00:00+00:00'),
        );
    }
}
