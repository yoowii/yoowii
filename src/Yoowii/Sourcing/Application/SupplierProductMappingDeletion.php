<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;

/** @deprecated Scheduled retention owns mapping deletion policy. */
final readonly class SupplierProductMappingDeletion
{
    public function __construct(private EntityManagerInterface $entityManager, private MappingVersionRetentionPolicy $retentionPolicy)
    {
    }

    public function delete(SupplierProductMappingVersion $mapping): void
    {
        if (!$this->retentionPolicy->canDelete($mapping, new \DateTimeImmutable('now', new \DateTimeZone('UTC')))) {
            throw new MappingDeletionRefused('Ce mapping ne respecte pas la politique de rétention et ne peut pas être supprimé.');
        }

        $this->entityManager->remove($mapping);
        $this->entityManager->flush();
    }
}
