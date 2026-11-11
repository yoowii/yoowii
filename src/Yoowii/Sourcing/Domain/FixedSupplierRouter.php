<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain;

use App\Yoowii\Sourcing\Domain\Exception\AmbiguousSupplierRoute;
use App\Yoowii\Sourcing\Domain\Exception\NoSupplierRoute;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

final class FixedSupplierRouter
{
    /**
     * @param iterable<SupplierRoute> $routes
     */
    public function select(string $productCode, \DateTimeImmutable $at, iterable $routes): SupplierRoute
    {
        return $this->rank($productCode, $at, $routes)[0];
    }

    /**
     * @param iterable<SupplierRoute> $routes
     *
     * @return non-empty-list<SupplierRoute>
     */
    public function rank(string $productCode, \DateTimeImmutable $at, iterable $routes): array
    {
        $eligibleRoutes = [];

        foreach ($routes as $route) {
            if ($route->isEligibleFor($productCode, $at)) {
                $eligibleRoutes[] = $route;
            }
        }

        if ([] === $eligibleRoutes) {
            throw new NoSupplierRoute($productCode);
        }

        usort(
            $eligibleRoutes,
            static fn (SupplierRoute $left, SupplierRoute $right): int => $left->priority() <=> $right->priority(),
        );

        foreach ($eligibleRoutes as $index => $route) {
            if (isset($eligibleRoutes[$index + 1]) && $route->priority() === $eligibleRoutes[$index + 1]->priority()) {
                throw new AmbiguousSupplierRoute($productCode, $route->priority());
            }
        }

        return $eligibleRoutes;
    }
}
