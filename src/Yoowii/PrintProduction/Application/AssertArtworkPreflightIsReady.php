<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AssertArtworkPreflightIsReady
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(PrintJob $job): void
    {
        $asset = $this->entityManager->getRepository(PrintAsset::class)->findOneBy([
            'printJob' => $job,
            'type' => PrintAssetType::CustomerArtwork,
            'supersededAt' => null,
        ], ['createdAt' => 'DESC']);
        if (!$asset instanceof PrintAsset) {
            throw new \DomainException('Un fichier client contrôlé est requis avant la production.');
        }
        $report = $this->entityManager->getRepository(PrintPreflightReport::class)->findOneBy(['printAsset' => $asset]);
        if (!$report instanceof PrintPreflightReport || PrintPreflightStatus::Pending === $report->status()) {
            throw new \DomainException('Le contrôle technique du fichier est encore en cours.');
        }
        if (PrintPreflightStatus::Failed === $report->status()) {
            throw new \DomainException('Le fichier client contient des erreurs bloquantes. Corrige-le avant de poursuivre.');
        }
    }
}
