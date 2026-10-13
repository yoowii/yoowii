<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintRevisionCreator;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class RealisaprintRevisionCreatorTest extends TestCase
{
    public function testItCreatesAnInactiveCopyWithoutChangingThePublishedDefinition(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_TEST', 'v1', [
            'format' => ['type' => 'code', 'allowed_values' => ['a4']],
        ], ['format']);
        $definition->activate();
        $supplierProduct = new SupplierProduct(new PrintSupplier('realisaprint', 'Realisaprint', SupplierIntegrationMode::Api), '42', 'Test');
        $route = new SupplierRoute('PRINT_TEST', $supplierProduct, 1, new \DateTimeImmutable());
        $publishedMapping = new SupplierProductMappingVersion($supplierProduct, 'PRINT_TEST', 'v1', ['realisaprint' => ['stock' => '1']], new \DateTimeImmutable(), null, $definition);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($publishedMapping);
        $repository->method('findBy')->willReturn([$publishedMapping]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $persisted = [];
        $entityManager->expects(self::exactly(2))->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void { $persisted[] = $entity; });
        $entityManager->expects(self::once())->method('flush');

        $revision = (new RealisaprintRevisionCreator($entityManager))->create($route);

        self::assertFalse($revision->isActive());
        self::assertSame('v2', $revision->version());
        self::assertNotSame($definition, $revision->printProductDefinition());
        self::assertFalse($revision->printProductDefinition()?->active());
        self::assertTrue($definition->active());
        self::assertCount(2, $persisted);
    }
}
