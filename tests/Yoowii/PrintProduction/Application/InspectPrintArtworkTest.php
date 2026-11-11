<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Application;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Application\InspectPrintArtwork;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use PHPUnit\Framework\TestCase;

final class InspectPrintArtworkTest extends TestCase
{
    public function testItRecognisesAnA5PdfWithThreeMillimetresOfBleed(): void
    {
        $now = new \DateTimeImmutable('2026-09-02T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', [
            'pricing' => ['configuration' => ['options' => ['format' => 'A5']]],
        ], $now);
        $pdf = "%PDF-1.4\n1 0 obj << /Type /Page /MediaBox [0 0 436.54 612.28] >> endobj\n";
        $asset = new PrintAsset($job, PrintAssetType::CustomerArtwork, 'flyer.pdf', 'PJ-TEST-1/customer.pdf', 'application/pdf', strlen($pdf), hash('sha256', $pdf), $now);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $pdf);
        rewind($stream);

        try {
            $result = (new InspectPrintArtwork())($asset, $stream);
        } finally {
            fclose($stream);
        }

        self::assertSame(PrintPreflightStatus::Passed, $result['status']);
        self::assertSame(1, $result['report']['metadata']['pages']);
        self::assertContains('Format A5 avec fond perdu de 3 mm détecté.', array_column($result['report']['checks'], 'message'));
    }

    public function testItRejectsAFileWithoutPdfSignature(): void
    {
        $now = new \DateTimeImmutable('2026-09-02T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-2', 'laboprint', 'FLYER', [], $now);
        $content = 'not a PDF';
        $asset = new PrintAsset($job, PrintAssetType::CustomerArtwork, 'broken.pdf', 'PJ-TEST-2/broken.pdf', 'application/pdf', strlen($content), hash('sha256', $content), $now);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $content);
        rewind($stream);

        try {
            $result = (new InspectPrintArtwork())($asset, $stream);
        } finally {
            fclose($stream);
        }

        self::assertSame(PrintPreflightStatus::Failed, $result['status']);
    }
}
