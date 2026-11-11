<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobCustomerMessage;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RejectPrintJobBat
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(PrintJob $job, string $reason): void
    {
        $now = new \DateTimeImmutable();
        $job->rejectBat($reason, $now);
        foreach ($this->entityManager->getRepository(PrintAsset::class)->findBy(['printJob' => $job, 'type' => PrintAssetType::Bat, 'supersededAt' => null]) as $asset) {
            $asset->supersede($now);
        }
        $this->entityManager->persist(new PrintJobCustomerMessage($job, 'bat_rejected', trim($reason), $now));
    }
}
