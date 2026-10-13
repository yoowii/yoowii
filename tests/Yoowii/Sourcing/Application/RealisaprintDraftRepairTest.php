<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintDraftRepair;
use App\Yoowii\Sourcing\Application\RealisaprintMappingCompleteness;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class RealisaprintDraftRepairTest extends TestCase
{
    public function testItRepairsANumericDefaultUsedByTheInitialPreview(): void
    {
        $supplierProduct = $this->createMock(SupplierProduct::class);
        $route = new SupplierRoute('PRINT_TEST', $supplierProduct, 1, new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $route->deactivate();
        $definition = new PersistedPrintProductDefinition('PRINT_TEST', 'v1', [
            'nombre_d_oeillets_par_visuel' => [
                'type' => 'float', 'required' => true, 'allowed_values' => [], 'minimum' => 1,
                'default' => 0, 'provider_variable' => 'OEILLETS', 'provider_type' => 'float',
            ],
        ], ['nombre_d_oeillets_par_visuel']);
        $catalog = new RealisaprintCatalogProduct('17', 'Bâche', new \DateTimeImmutable());
        $catalog->refreshConfiguration(['stocks' => ['1' => 'Standard'], 'variables' => [
            'OEILLETS' => ['type' => 'float', 'values' => false],
        ]], new \DateTimeImmutable());
        $previous = new SupplierProductMappingVersion($supplierProduct, 'PRINT_TEST', 'v1', ['realisaprint' => [
            'product' => '17', 'stock' => '1', 'variables' => [
                'OEILLETS' => ['option' => 'nombre_d_oeillets_par_visuel', 'values' => []],
            ],
        ]], new \DateTimeImmutable());

        $product = $this->createMock(Product::class);
        $product->method('isEnabled')->willReturn(false);
        $productRepository = $this->createMock(EntityRepository::class);
        $productRepository->method('findOneBy')->willReturn($product);
        $mappingRepository = $this->createMock(EntityRepository::class);
        $mappingRepository->method('findOneBy')->willReturn($previous);
        $mappingRepository->method('findBy')->willReturn([$previous]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => match ($class) {
            Product::class => $productRepository,
            SupplierProductMappingVersion::class => $mappingRepository,
        });
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        (new RealisaprintDraftRepair($entityManager, new RealisaprintMappingCompleteness()))->repair(
            $route,
            $catalog,
            $definition,
            [],
            ['nombre_d_oeillets_par_visuel' => '2'],
        );

        self::assertSame(2.0, $definition->options()['nombre_d_oeillets_par_visuel']['default']);
    }
}
