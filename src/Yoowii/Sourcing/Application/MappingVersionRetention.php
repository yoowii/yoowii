<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;

final readonly class MappingVersionRetention
{
    public function __construct(private EntityManagerInterface $entityManager, private MappingVersionRetentionPolicy $policy)
    {
    }

    /** @return array{archived: int, deleted: int} */
    public function apply(\DateTimeImmutable $now, bool $dryRun): array
    {
        $archived = 0;
        $deleted = 0;
        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([], ['effectiveFrom' => 'ASC', 'id' => 'ASC']) as $mapping) {
            if (!$mapping->isActive() && null !== $mapping->publishedAt() && null === $mapping->archivedAt()) {
                ++$archived;
                if (!$dryRun) {
                    $mapping->archive($now);
                }
                continue;
            }
            if ($this->policy->canDelete($mapping, $now)) {
                ++$deleted;
                if (!$dryRun) {
                    $this->entityManager->remove($mapping);
                }
            }
        }
        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return ['archived' => $archived, 'deleted' => $deleted];
    }
}
