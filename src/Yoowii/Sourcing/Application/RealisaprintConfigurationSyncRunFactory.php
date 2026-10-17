<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintConfigurationSyncRun;
use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintConfigurationSyncRunFactory
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function create(RealisaprintConfigurationSyncMode $mode): RealisaprintConfigurationSyncRun
    {
        $existing = $this->entityManager->getRepository(RealisaprintConfigurationSyncRun::class)->findOneBy(['activeLock' => 'catalog-configurations']);
        if ($existing instanceof RealisaprintConfigurationSyncRun) {
            throw new \DomainException('Une synchronisation des configurations Realisaprint est déjà en cours.');
        }
        $run = new RealisaprintConfigurationSyncRun($mode, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }
}
