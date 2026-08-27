<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class SupplierProductMappingVersionTest extends TestCase
{
    public function testItIsEffectiveOnlyInsideItsValidityPeriod(): void
    {
        $mapping = new SupplierProductMappingVersion(
            self::supplierProduct(),
            'PRINT_FLYER',
            '2026-09-01',
            ['format' => ['a5' => 'DIN-A5']],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        );

        self::assertFalse($mapping->isEffectiveAt(new \DateTimeImmutable('2026-08-31T23:59:59+00:00')));
        self::assertTrue($mapping->isEffectiveAt(new \DateTimeImmutable('2026-09-15T00:00:00+00:00')));
        self::assertFalse($mapping->isEffectiveAt(new \DateTimeImmutable('2026-10-01T00:00:00+00:00')));

        $mapping->deactivate();

        self::assertFalse($mapping->isEffectiveAt(new \DateTimeImmutable('2026-09-15T00:00:00+00:00')));
    }

    public function testItRejectsNonJsonMappingValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value at "configuration_mapping.callback" is not JSON-compatible.');

        new SupplierProductMappingVersion(
            self::supplierProduct(),
            'PRINT_FLYER',
            '2026-09-01',
            ['callback' => static fn (): null => null],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );
    }

    private static function supplierProduct(): SupplierProduct
    {
        return new SupplierProduct(
            new PrintSupplier('laboprint', 'Laboprint', SupplierIntegrationMode::Matrix),
            'FLYER_STANDARD',
            'Flyer standard',
        );
    }
}
