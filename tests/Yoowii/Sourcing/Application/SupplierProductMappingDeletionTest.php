<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\MappingDeletionRefused;
use App\Yoowii\Sourcing\Application\MappingVersionRetentionPolicy;
use App\Yoowii\Sourcing\Application\SupplierProductMappingDeletion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SupplierProductMappingDeletionTest extends TestCase
{
    public function testItDeletesAnOldInactiveUnpublishedAndUnreferencedMapping(): void
    {
        $entityManager = $this->entityManager(false);
        $mapping = $this->mapping(false, null, new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')));
        $entityManager->expects(self::once())->method('remove')->with($mapping);
        $entityManager->expects(self::once())->method('flush');

        (new SupplierProductMappingDeletion($entityManager, new MappingVersionRetentionPolicy($entityManager->getConnection())))->delete($mapping);
    }

    public function testItRefusesAPublishedMapping(): void
    {
        $entityManager = $this->entityManager(false);
        $mapping = $this->mapping(false, new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')), new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')));
        $entityManager->expects(self::never())->method('remove');

        $this->expectException(MappingDeletionRefused::class);
        (new SupplierProductMappingDeletion($entityManager, new MappingVersionRetentionPolicy($entityManager->getConnection())))->delete($mapping);
    }

    /** @return EntityManagerInterface&MockObject */
    private function entityManager(bool $hasOrder): EntityManagerInterface
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($hasOrder ? 1 : 0);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }

    private function mapping(bool $active, ?\DateTimeImmutable $publishedAt, \DateTimeImmutable $effectiveFrom): SupplierProductMappingVersion
    {
        $supplierProduct = $this->createMock(SupplierProduct::class);
        $supplierProduct->method('id')->willReturn(12);
        $supplierProduct->method('code')->willReturn('SUPPLIER-PRODUCT');
        $mapping = $this->createMock(SupplierProductMappingVersion::class);
        $mapping->method('isActive')->willReturn($active);
        $mapping->method('publishedAt')->willReturn($publishedAt);
        $mapping->method('id')->willReturn(42);
        $mapping->method('supplierProduct')->willReturn($supplierProduct);
        $mapping->method('yoowiiProductCode')->willReturn('POSTER');
        $mapping->method('version')->willReturn('v1');
        $mapping->method('effectiveFrom')->willReturn($effectiveFrom);

        return $mapping;
    }
}
