<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application\MessageHandler;

use App\Yoowii\PrintProduction\Application\InspectPrintArtwork;
use App\Yoowii\PrintProduction\Application\Message\PreflightPrintAsset;
use App\Yoowii\PrintProduction\Application\PrintAssetStorage;
use App\Yoowii\PrintProduction\Application\RecordPrintJobActivity;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PreflightPrintAssetHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private PrintAssetStorage $storage, private InspectPrintArtwork $inspect, private RecordPrintJobActivity $activity)
    {
    }

    public function __invoke(PreflightPrintAsset $message): void
    {
        $asset = $this->entityManager->find(PrintAsset::class, $message->assetId);
        if (!$asset instanceof PrintAsset || PrintAssetType::CustomerArtwork !== $asset->type() || !hash_equals($asset->checksum(), $message->assetChecksum)) {
            return;
        }
        $report = $this->entityManager->getRepository(PrintPreflightReport::class)->findOneBy(['printAsset' => $asset]);
        if (!$report instanceof PrintPreflightReport) {
            return;
        }

        try {
            $stream = $this->storage->open($asset->storageKey());

            try {
                $result = ($this->inspect)($asset, $stream);
            } finally {
                fclose($stream);
            }
            $report->complete($result['status'], $result['report'], new \DateTimeImmutable());
            ($this->activity)($asset->printJob(), 'artwork_preflight_completed', 'system', ['asset_id' => $asset->id(), 'status' => $result['status']->value]);
        } catch (\Throwable) {
            $report->complete(PrintPreflightStatus::Failed, ['checks' => [['code' => 'technical_analysis', 'severity' => 'error', 'message' => 'L’analyse technique a échoué : contrôle opérateur requis.']], 'metadata' => []], new \DateTimeImmutable());
            ($this->activity)($asset->printJob(), 'artwork_preflight_failed', 'system', ['asset_id' => $asset->id(), 'status' => PrintPreflightStatus::Failed->value]);
        }
        $this->entityManager->flush();
    }
}
