<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain;

use App\Yoowii\Sourcing\Domain\Exception\AmbiguousSupplierRoute;
use App\Yoowii\Sourcing\Domain\Exception\NoSupplierRoute;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class FixedSupplierRouterTest extends TestCase
{
    public function testItSelectsTheEligibleRouteWithTheLowestPriority(): void
    {
        $laboprint = self::route('laboprint', 10);
        $fallback = self::route('123imprim', 20);

        $selected = (new FixedSupplierRouter())->select(
            'PRINT_FLYER',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            [$fallback, $laboprint],
        );

        self::assertSame($laboprint, $selected);
    }

    public function testItIgnoresAnInactiveSupplier(): void
    {
        $primary = self::route('laboprint', 10);
        $primary->supplierProduct()->supplier()->deactivate();
        $fallback = self::route('123imprim', 20);

        $selected = (new FixedSupplierRouter())->select(
            'PRINT_FLYER',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            [$primary, $fallback],
        );

        self::assertSame($fallback, $selected);
    }

    public function testItRejectsRoutesWithTheSamePriority(): void
    {
        $this->expectException(AmbiguousSupplierRoute::class);

        (new FixedSupplierRouter())->select(
            'PRINT_FLYER',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            [self::route('laboprint', 10), self::route('123imprim', 10)],
        );
    }

    public function testItFailsWhenNoRouteIsAvailable(): void
    {
        $this->expectException(NoSupplierRoute::class);

        (new FixedSupplierRouter())->select(
            'PRINT_BUSINESS_CARD',
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            [self::route('laboprint', 10)],
        );
    }

    private static function route(string $supplierCode, int $priority): SupplierRoute
    {
        $supplier = new PrintSupplier($supplierCode, $supplierCode, SupplierIntegrationMode::Matrix);
        $supplierProduct = new SupplierProduct($supplier, 'FLYER_STANDARD', 'Flyer standard');

        return new SupplierRoute(
            'PRINT_FLYER',
            $supplierProduct,
            $priority,
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
        );
    }
}
