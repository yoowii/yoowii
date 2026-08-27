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

        if (isset($eligibleRoutes[1]) && $eligibleRoutes[0]->priority() === $eligibleRoutes[1]->priority()) {
            throw new AmbiguousSupplierRoute($productCode, $eligibleRoutes[0]->priority());
        }

        return $eligibleRoutes[0];
    }
}
