<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Infrastructure\Doctrine\Repository;

use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSupplierPricingMatrixVersionRepository implements SupplierPricingMatrixVersionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function findSelectableFor(
        array $supplierProducts,
        string $currencyCode,
        \DateTimeImmutable $at,
    ): array {
        if ([] === $supplierProducts) {
            return [];
        }

        /** @var list<SupplierPricingMatrixVersion> $matrices */
        $matrices = $this->entityManager
            ->createQueryBuilder()
            ->select('matrix')
            ->from(SupplierPricingMatrixVersion::class, 'matrix')
            ->andWhere('matrix.supplierProduct IN (:supplierProducts)')
            ->andWhere('matrix.currencyCode = :currencyCode')
            ->andWhere('matrix.status = :status')
            ->andWhere('matrix.effectiveFrom <= :at')
            ->setParameter('supplierProducts', $supplierProducts)
            ->setParameter('currencyCode', $currencyCode)
            ->setParameter('status', PricingMatrixStatus::Active->value)
            ->setParameter('at', $at)
            ->orderBy('matrix.effectiveFrom', 'DESC')
            ->addOrderBy('matrix.importedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $matrices;
    }
}
