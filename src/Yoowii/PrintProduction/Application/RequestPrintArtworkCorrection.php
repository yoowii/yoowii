<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobCustomerMessage;
use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RequestPrintArtworkCorrection
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(PrintJob $job, PrintPreflightReport $report, string $message): void
    {
        $message = trim($message);
        if ($report->printAsset()->printJob() !== $job || !$report->printAsset()->isActive()) {
            throw new \DomainException('Le rapport technique ne concerne pas le fichier client actif.');
        }
        if (!$job->canAcceptCustomerArtwork()) {
            throw new \DomainException('Le fichier est verrouillé après la publication du BAT. Rouvre le dossier avant de demander une correction.');
        }
        if (!in_array($report->status(), [PrintPreflightStatus::Warning, PrintPreflightStatus::Failed], true)) {
            throw new \DomainException('Une correction client ne peut être demandée que pour un rapport en avertissement ou en erreur.');
        }
        if ('' === $message) {
            throw new \InvalidArgumentException('Le message de correction est obligatoire.');
        }

        $this->entityManager->persist(new PrintJobCustomerMessage($job, 'preflight_correction_requested', $message, new \DateTimeImmutable()));
    }
}
