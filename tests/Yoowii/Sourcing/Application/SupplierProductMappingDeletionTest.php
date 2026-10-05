<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\MappingDeletionRefused;
use App\Yoowii\Sourcing\Application\SupplierProductMappingDeletion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SupplierProductMappingDeletionTest extends TestCase
{
    public function testItDeletesAnInactiveSupersededAndUnreferencedMapping(): void
    {
        $entityManager = $this->entityManagerWithReferences(['has_newer' => 1, 'has_route' => 0, 'has_validation' => 0, 'has_order' => 0]);
        $mapping = $this->mapping(false);
        $entityManager->expects(self::once())->method('remove')->with($mapping);
        $entityManager->expects(self::once())->method('flush');

        (new SupplierProductMappingDeletion($entityManager))->delete($mapping);
    }

    public function testItRefusesAnActiveMapping(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getConnection');

        $this->expectException(MappingDeletionRefused::class);
        $this->expectExceptionMessage('encore actif');

        (new SupplierProductMappingDeletion($entityManager))->delete($this->mapping(true));
    }

    /** @param array{has_newer: int, has_route: int, has_validation: int, has_order: int} $references */
    #[DataProvider('protectedReferenceProvider')]
    public function testItRefusesMappingsThatMustBeRetained(array $references, string $message): void
    {
        $entityManager = $this->entityManagerWithReferences($references);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $this->expectException(MappingDeletionRefused::class);
        $this->expectExceptionMessage($message);

        (new SupplierProductMappingDeletion($entityManager))->delete($this->mapping(false));
    }

    /** @return iterable<string, array{array{has_newer: int, has_route: int, has_validation: int, has_order: int}, string}> */
    public static function protectedReferenceProvider(): iterable
    {
        yield 'last version' => [['has_newer' => 0, 'has_route' => 0, 'has_validation' => 0, 'has_order' => 0], 'dernière version'];
        yield 'supplier route' => [['has_newer' => 1, 'has_route' => 1, 'has_validation' => 0, 'has_order' => 0], 'route fournisseur'];
        yield 'saved validation' => [['has_newer' => 1, 'has_route' => 0, 'has_validation' => 1, 'has_order' => 0], 'validation à conserver'];
        yield 'order' => [['has_newer' => 1, 'has_route' => 0, 'has_validation' => 0, 'has_order' => 1], 'référencé par une commande'];
    }

    /**
     * @param array{has_newer: int, has_route: int, has_validation: int, has_order: int} $references
     *
     * @return EntityManagerInterface&MockObject
     */
    private function entityManagerWithReferences(array $references): EntityManagerInterface
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('fetchAssociative')->willReturn($references);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function mapping(bool $active): SupplierProductMappingVersion
    {
        $supplierProduct = $this->createMock(SupplierProduct::class);
        $supplierProduct->method('id')->willReturn(12);
        $supplierProduct->method('code')->willReturn('SUPPLIER-PRODUCT');
        $mapping = $this->createMock(SupplierProductMappingVersion::class);
        $mapping->method('isActive')->willReturn($active);
        $mapping->method('id')->willReturn(42);
        $mapping->method('supplierProduct')->willReturn($supplierProduct);
        $mapping->method('yoowiiProductCode')->willReturn('POSTER');
        $mapping->method('version')->willReturn('v1');
        $mapping->method('effectiveFrom')->willReturn(new \DateTimeImmutable('2026-10-01 12:00:00', new \DateTimeZone('UTC')));

        return $mapping;
    }
}
