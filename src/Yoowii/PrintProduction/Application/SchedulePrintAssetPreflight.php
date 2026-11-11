<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Application\Message\PreflightPrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SchedulePrintAssetPreflight
{
    public function __construct(private EntityManagerInterface $entityManager, private MessageBusInterface $messageBus)
    {
    }

    public function __invoke(PrintAsset $asset): PrintPreflightReport
    {
        if (PrintAssetType::CustomerArtwork !== $asset->type() || null === $asset->id()) {
            throw new \InvalidArgumentException('Only persisted customer artwork can be preflighted.');
        }

        $report = $this->entityManager->getRepository(PrintPreflightReport::class)->findOneBy(['printAsset' => $asset]);
        if (!$report instanceof PrintPreflightReport) {
            $report = new PrintPreflightReport($asset, $asset->checksum(), new \DateTimeImmutable());
            $this->entityManager->persist($report);
        } else {
            $report->restart();
        }
        $this->entityManager->flush();
        $this->messageBus->dispatch(new PreflightPrintAsset($asset->id(), $asset->checksum()));

        return $report;
    }
}
