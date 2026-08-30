<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobNote;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AddPrintJobNote
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(PrintJob $job, string $message, ?string $author): void
    {
        $this->entityManager->persist(new PrintJobNote($job, trim($message), $author, new \DateTimeImmutable()));
    }
}
