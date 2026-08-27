<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class SupplierPricingMatrixVersionTest extends TestCase
{
    public function testItActivatesAndBecomesSelectableOnItsEffectiveDate(): void
    {
        $matrix = self::matrix();

        self::assertSame(PricingMatrixStatus::Draft, $matrix->status());
        self::assertFalse($matrix->isSelectableAt(new \DateTimeImmutable('2026-09-01T00:00:00+00:00')));

        $matrix->activate(new \DateTimeImmutable('2026-08-28T00:00:00+00:00'));

        self::assertSame(PricingMatrixStatus::Active, $matrix->status());
        self::assertFalse($matrix->isSelectableAt(new \DateTimeImmutable('2026-08-31T23:59:59+00:00')));
        self::assertTrue($matrix->isSelectableAt(new \DateTimeImmutable('2026-09-01T00:00:00+00:00')));
    }

    public function testArchivedMatrixCannotBeReactivated(): void
    {
        $matrix = self::matrix();
        $matrix->archive();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('An archived pricing matrix cannot be activated.');

        $matrix->activate(new \DateTimeImmutable('2026-09-01T00:00:00+00:00'));
    }

    public function testChecksumDoesNotDependOnObjectKeyOrder(): void
    {
        $product = self::supplierProduct();

        $first = new SupplierPricingMatrixVersion(
            $product,
            '2026-09-01',
            'EUR',
            ['format' => 'a5', 'prices' => ['1000' => 3100, '500' => 2100]],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        );
        $second = new SupplierPricingMatrixVersion(
            $product,
            '2026-09-01',
            'EUR',
            ['prices' => ['500' => 2100, '1000' => 3100], 'format' => 'a5'],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        );

        self::assertSame($first->checksum(), $second->checksum());
    }

    private static function matrix(): SupplierPricingMatrixVersion
    {
        return new SupplierPricingMatrixVersion(
            self::supplierProduct(),
            '2026-09-01',
            'EUR',
            ['rows' => [['format' => 'a5', 'quantity' => 1000, 'cost' => 3100]]],
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
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
