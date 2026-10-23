<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\SourcingProductOverviewBuilder;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class SourcingProductOverviewBuilderTest extends TestCase
{
    public function testManualSupplierWithoutAMappingIsNotBlocked(): void
    {
        $supplier = new PrintSupplier('manual', 'Manuel', SupplierIntegrationMode::Manual);
        $product = new SupplierProduct($supplier, 'manual-flyer', 'Flyer');
        $route = new SupplierRoute('PRINT_FLYER', $product, 1, new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $overview = (new SourcingProductOverviewBuilder())->build([$route], [], [], new \DateTimeImmutable('2026-10-14T00:00:00+00:00'));

        self::assertSame([], $overview['PRINT_FLYER']['errors']);
        self::assertSame(['Aucune route de secours éligible.'], $overview['PRINT_FLYER']['recommendations']);
    }

    public function testFutureMappingIsReportedSeparatelyFromCurrentHealth(): void
    {
        $supplier = new PrintSupplier('api', 'API', SupplierIntegrationMode::Api);
        $product = new SupplierProduct($supplier, 'api-flyer', 'Flyer');
        $route = new SupplierRoute('PRINT_FLYER', $product, 1, new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $mapping = new SupplierProductMappingVersion($product, 'PRINT_FLYER', 'v2', [], new \DateTimeImmutable('2026-11-01T00:00:00+00:00'));

        $overview = (new SourcingProductOverviewBuilder())->build([$route], [$mapping], [], new \DateTimeImmutable('2026-10-14T00:00:00+00:00'));

        self::assertContains('Le fournisseur API principal n’a pas de mapping applicable.', $overview['PRINT_FLYER']['errors']);
        self::assertSame([$mapping], $overview['PRINT_FLYER']['future']['mappings']);
        self::assertContains('Des changements sont planifiés.', $overview['PRINT_FLYER']['recommendations']);
    }

    public function testAnUnpublishedCurrentApiMappingDoesNotMakeTheRouteStorefrontReady(): void
    {
        $supplier = new PrintSupplier('api', 'API', SupplierIntegrationMode::Api);
        $product = new SupplierProduct($supplier, 'api-flyer', 'Flyer');
        $route = new SupplierRoute('PRINT_FLYER', $product, 1, new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $mapping = new SupplierProductMappingVersion($product, 'PRINT_FLYER', 'v1', [], new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $overview = (new SourcingProductOverviewBuilder())->build([$route], [$mapping], [], new \DateTimeImmutable('2026-10-14T00:00:00+00:00'));

        self::assertSame([], $overview['PRINT_FLYER']['current_mappings']);
        self::assertContains('Le fournisseur API principal n’a pas de mapping applicable.', $overview['PRINT_FLYER']['errors']);
    }
}
