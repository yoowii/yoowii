<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobActivity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RecordPrintJobActivity
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @param array<string, scalar|null> $details */
    public function __invoke(PrintJob $job, string $action, ?string $actor, array $details = []): void
    {
        $this->entityManager->persist(new PrintJobActivity($job, $action, $actor, $details, new \DateTimeImmutable()));
    }
}
