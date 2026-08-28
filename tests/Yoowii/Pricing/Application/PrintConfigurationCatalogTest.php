<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\PrintConfigurationCatalog;
use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Sourcing\Application\Import\PrintPricingMatrixCsvImporter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class PrintConfigurationCatalogTest extends TestCase
{
    public function testItBuildsDistinctOptionsFromActiveGenericMatrices(): void
    {
        $definition = BuiltInPrintProductDefinitions::flyer();
        $supplierProduct = new SupplierProduct(
            new PrintSupplier('laboprint', 'Laboprint', SupplierIntegrationMode::Matrix),
            'FLYER_STANDARD',
            'Flyer standard',
        );
        $route = new SupplierRoute(
            'PRINT_FLYER',
            $supplierProduct,
            10,
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );
        $import = (new PrintPricingMatrixCsvImporter())->import(
            $definition,
            $supplierProduct,
            'v1',
            'EUR',
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-01T00:00:00+00:00'),
            <<<'CSV'
format;sides;paper;grammage;quantity;finishing;production_cost;shipping_cost
a5;two_sided;coated_gloss;135;1000;none;2000;500
a6;two_sided;coated_gloss;135;1000;none;1600;500
a5;two_sided;coated_gloss;135;2500;none;3000;600
CSV,
        );
        $import->matrix()->activate(new \DateTimeImmutable('2026-08-02T00:00:00+00:00'));
        $catalog = new PrintConfigurationCatalog(
            new class([$route]) implements SupplierRouteRepository {
                /** @param list<SupplierRoute> $routes */
                public function __construct(private readonly array $routes)
                {
                }

                public function findCandidates(string $productCode, \DateTimeImmutable $at): array
                {
                    return $this->routes;
                }
            },
            new class([$import->matrix()]) implements SupplierPricingMatrixVersionRepository {
                /** @param list<SupplierPricingMatrixVersion> $matrices */
                public function __construct(private readonly array $matrices)
                {
                }

                public function findSelectableFor(array $supplierProducts, string $currencyCode, \DateTimeImmutable $at): array
                {
                    return $this->matrices;
                }
            },
        );

        $options = $catalog->availableOptions(
            $definition,
            'EUR',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
        );

        self::assertSame(['a5', 'a6'], $options['format']);
        self::assertSame([1000, 2500], $options['quantity']);
        self::assertSame(['two_sided'], $options['sides']);
    }
}
