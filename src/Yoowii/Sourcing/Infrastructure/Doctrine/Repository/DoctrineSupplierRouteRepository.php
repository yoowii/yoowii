<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Infrastructure\Doctrine\Repository;

use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSupplierRouteRepository implements SupplierRouteRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findCandidates(string $productCode, \DateTimeImmutable $at): array
    {
        /** @var list<SupplierRoute> $routes */
        $routes = $this->entityManager
            ->createQueryBuilder()
            ->select('route', 'supplierProduct', 'supplier')
            ->from(SupplierRoute::class, 'route')
            ->innerJoin('route.supplierProduct', 'supplierProduct')
            ->innerJoin('supplierProduct.supplier', 'supplier')
            ->andWhere('route.yoowiiProductCode = :productCode')
            ->andWhere('route.active = true')
            ->andWhere('supplierProduct.active = true')
            ->andWhere('supplier.active = true')
            ->andWhere('route.effectiveFrom <= :at')
            ->andWhere('route.effectiveUntil IS NULL OR route.effectiveUntil > :at')
            ->setParameter('productCode', $productCode)
            ->setParameter('at', $at)
            ->orderBy('route.priority', 'ASC')
            ->getQuery()
            ->getResult();

        return $routes;
    }
}
