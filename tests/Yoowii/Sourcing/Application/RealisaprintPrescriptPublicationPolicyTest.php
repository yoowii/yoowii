<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\RealisaprintPrescriptPublicationPolicy;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class RealisaprintPrescriptPublicationPolicyTest extends TestCase
{
    public function testItAcceptsTheMinimalExplicitPrescriptMapping(): void
    {
        [$catalog, $route, $mapping] = $this->fixtures(['product' => '42', 'stock' => '837', 'configurator' => 'prescript']);

        self::assertNull((new RealisaprintPrescriptPublicationPolicy())->assertPublishable($mapping, $route, $catalog));
    }

    public function testItDoesNotTreatClassicAsPrescriptAfterAnError(): void
    {
        [$catalog, $route, $mapping] = $this->fixtures(['product' => '42', 'stock' => '837', 'configurator' => 'classic']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('choisir explicitement le configurateur Préscript');

        (new RealisaprintPrescriptPublicationPolicy())->assertPublishable($mapping, $route, $catalog);
    }

    public function testItRejectsAStockNoLongerAdvertisedForPrescript(): void
    {
        [$catalog, $route, $mapping] = $this->fixtures(['product' => '42', 'stock' => '1228', 'configurator' => 'prescript']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('n’est plus disponible dans le catalogue Préscript synchronisé');

        (new RealisaprintPrescriptPublicationPolicy())->assertPublishable($mapping, $route, $catalog);
    }

    /**
     * @param array<string, string> $provider
     *
     * @return array{0: RealisaprintCatalogProduct, 1: SupplierRoute, 2: SupplierProductMappingVersion}
     */
    private function fixtures(array $provider): array
    {
        $supplier = new PrintSupplier('realisaprint', 'Realisaprint', SupplierIntegrationMode::Api);
        $supplierProduct = new SupplierProduct($supplier, '42', 'Agenda');
        $route = new SupplierRoute('PRINT_AGENDA', $supplierProduct, 1, new \DateTimeImmutable('2026-10-16T00:00:00+00:00'));
        $mapping = new SupplierProductMappingVersion($supplierProduct, 'PRINT_AGENDA', 'v1', ['realisaprint' => $provider], new \DateTimeImmutable('2026-10-16T00:00:00+00:00'));
        $catalog = new RealisaprintCatalogProduct('42', 'Agenda', new \DateTimeImmutable('2026-10-16T00:00:00+00:00'));
        $prescriptStocks = array_combine(['837'], ['Agenda']);
        $catalog->refreshPrescriptStocks($prescriptStocks);

        return [$catalog, $route, $mapping];
    }
}
