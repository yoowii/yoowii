<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Repository;

use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

interface SupplierRouteRepository
{
    /** @return list<SupplierRoute> */
    public function findCandidates(string $productCode, \DateTimeImmutable $at): array;
}
