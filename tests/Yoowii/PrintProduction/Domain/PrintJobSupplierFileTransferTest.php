<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Domain;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierFileTransfer;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use PHPUnit\Framework\TestCase;

final class PrintJobSupplierFileTransferTest extends TestCase
{
    public function testItRecordsASimulatedFtpUploadWithoutPretendingTheFileWasUploaded(): void
    {
        $at = new \DateTimeImmutable('2026-09-04T10:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-FTP-1', 'realisaprint', 'FLYER', ['schema_version' => 1], $at);
        $submission = new PrintJobSupplierSubmission($job, 'realisaprint:PJ-FTP-1', $at, $at);
        $asset = new PrintAsset($job, PrintAssetType::CustomerArtwork, 'recto.pdf', 'PJ-FTP-1/customer_artwork', 'application/pdf', 12, str_repeat('a', 64), $at);
        $transfer = new PrintJobSupplierFileTransfer($submission, $asset, 'PJ-FTP-1/impression/recto/recto.pdf', $at, $at);
        $transfer->markUploaded($at, true);

        self::assertSame('simulated', $transfer->status());
        self::assertSame(1, $transfer->attemptCount());
    }
}
