<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Validation;

use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

final class SupplierRouteSchedule
{
    /** @param iterable<SupplierRoute> $routes */
    public function hasConflict(
        iterable $routes,
        string $productCode,
        int $priority,
        \DateTimeImmutable $effectiveFrom,
        ?\DateTimeImmutable $effectiveUntil,
        ?int $excludedRouteId = null,
    ): bool {
        foreach ($routes as $route) {
            if (!$route->isActive()) {
                continue;
            }

            if (null !== $excludedRouteId && $route->id() === $excludedRouteId) {
                continue;
            }

            if ($route->yoowiiProductCode() !== $productCode || $route->priority() !== $priority) {
                continue;
            }

            $newEndsBeforeExisting = null !== $effectiveUntil && $effectiveUntil <= $route->effectiveFrom();
            $existingEndsBeforeNew = null !== $route->effectiveUntil() && $route->effectiveUntil() <= $effectiveFrom;

            if (!$newEndsBeforeExisting && !$existingEndsBeforeNew) {
                return true;
            }
        }

        return false;
    }
}
