<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application\Validation;

use App\Yoowii\Sourcing\Application\Validation\SupplierRouteSchedule;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class SupplierRouteScheduleTest extends TestCase
{
    public function testItDetectsAnOverlappingActiveRouteWithTheSamePriority(): void
    {
        $route = self::route(
            10,
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        );

        self::assertTrue((new SupplierRouteSchedule())->hasConflict(
            [$route],
            'PRINT_FLYER',
            10,
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            null,
        ));
    }

    public function testAdjacentPeriodsDoNotConflict(): void
    {
        $route = self::route(
            10,
            new \DateTimeImmutable('2026-09-01T00:00:00+00:00'),
            new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
        );

        self::assertFalse((new SupplierRouteSchedule())->hasConflict(
            [$route],
            'PRINT_FLYER',
            10,
            new \DateTimeImmutable('2026-10-01T00:00:00+00:00'),
            null,
        ));
    }

    public function testItIgnoresInactiveAndDifferentPriorityRoutes(): void
    {
        $inactive = self::route(10, new \DateTimeImmutable('2026-09-01T00:00:00+00:00'), null);
        $inactive->deactivate();
        $fallback = self::route(20, new \DateTimeImmutable('2026-09-01T00:00:00+00:00'), null);

        self::assertFalse((new SupplierRouteSchedule())->hasConflict(
            [$inactive, $fallback],
            'PRINT_FLYER',
            10,
            new \DateTimeImmutable('2026-09-15T00:00:00+00:00'),
            null,
        ));
    }

    private static function route(
        int $priority,
        \DateTimeImmutable $effectiveFrom,
        ?\DateTimeImmutable $effectiveUntil,
    ): SupplierRoute {
        $supplier = new PrintSupplier('laboprint', 'Laboprint', SupplierIntegrationMode::Matrix);
        $supplierProduct = new SupplierProduct($supplier, 'FLYER_STANDARD', 'Flyer standard');

        return new SupplierRoute(
            'PRINT_FLYER',
            $supplierProduct,
            $priority,
            $effectiveFrom,
            $effectiveUntil,
        );
    }
}
