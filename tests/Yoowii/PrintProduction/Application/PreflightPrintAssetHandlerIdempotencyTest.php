<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Application;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Application\InspectPrintArtwork;
use App\Yoowii\PrintProduction\Application\Message\PreflightPrintAsset;
use App\Yoowii\PrintProduction\Application\MessageHandler\PreflightPrintAssetHandler;
use App\Yoowii\PrintProduction\Application\PrintAssetStorage;
use App\Yoowii\PrintProduction\Application\RecordPrintJobActivity;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobActivity;
use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class PreflightPrintAssetHandlerIdempotencyTest extends TestCase
{
    public function testRedeliveryDoesNotReinspectCompletedReportOrDuplicateActivity(): void
    {
        [$asset, $report, $payload] = $this->fixtures();

        $manager = $this->createMock(EntityManagerInterface::class);
        $this->mockTransactions($manager);
        $repository = $this->createMock(EntityRepository::class);
        $manager->method('find')->willReturn($asset);
        $manager->method('getRepository')->willReturn($repository);
        $repository->method('findOneBy')->willReturn($report);
        $manager->expects(self::exactly(2))->method('refresh')->with($report, LockMode::PESSIMISTIC_WRITE);
        $manager->expects(self::once())->method('persist')->with(self::isInstanceOf(PrintJobActivity::class));
        $manager->expects(self::exactly(2))->method('flush');

        $storage = $this->createMock(PrintAssetStorage::class);
        $storage->expects(self::once())->method('open')->willReturnCallback(static function () use ($payload) {
            $stream = fopen('php://temp', 'w+b');
            if (false === $stream) {
                throw new \RuntimeException('Unable to create test stream');
            }
            fwrite($stream, $payload);
            rewind($stream);

            return $stream;
        });

        $handler = new PreflightPrintAssetHandler(
            $manager,
            $storage,
            new InspectPrintArtwork(),
            new RecordPrintJobActivity($manager),
        );
        $message = new PreflightPrintAsset(42, $asset->checksum());

        $handler($message);
        self::assertNotSame(PrintPreflightStatus::Pending, $report->status());
        $firstAnalysisDate = $report->analysedAt();
        self::assertNotNull($firstAnalysisDate);

        // Simulate a lost ACK / a redelivered copy of the same message.
        $handler($message);
        self::assertSame($firstAnalysisDate, $report->analysedAt());
    }

    public function testCompletedFailureIsAlsoNotRetriedWithoutExplicitRestart(): void
    {
        [$asset, $report] = $this->fixtures();
        $report->complete(PrintPreflightStatus::Failed, ['checks' => []], new \DateTimeImmutable());

        $this->assertSkippedWithoutSideEffects($asset, $report, new PreflightPrintAsset(42, $asset->checksum()));
    }

    public function testPendingReportWithDifferentChecksumIgnoresStaleMessage(): void
    {
        [$asset] = $this->fixtures();
        $report = new PrintPreflightReport($asset, str_repeat('f', 64), new \DateTimeImmutable());

        $this->assertSkippedWithoutSideEffects($asset, $report, new PreflightPrintAsset(42, $asset->checksum()));
        self::assertSame(PrintPreflightStatus::Pending, $report->status());
    }

    public function testAnotherConsumerCompletingReportWhileWaitingForLockIsRespected(): void
    {
        [$asset, $report] = $this->fixtures();
        $manager = $this->createMock(EntityManagerInterface::class);
        $this->mockTransactions($manager);
        $repository = $this->createMock(EntityRepository::class);
        $manager->method('find')->willReturn($asset);
        $manager->method('getRepository')->willReturn($repository);
        $repository->method('findOneBy')->willReturn($report);

        // Simulate an ORM refresh that sees the first consumer's committed
        // status only after acquiring the pessimistic lock.
        $manager->expects(self::once())->method('refresh')
            ->with($report, LockMode::PESSIMISTIC_WRITE)
            ->willReturnCallback(static function () use ($report): void {
                $report->complete(PrintPreflightStatus::Passed, ['checks' => []], new \DateTimeImmutable());
            });
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::once())->method('flush');
        $storage = $this->createMock(PrintAssetStorage::class);
        $storage->expects(self::never())->method('open');

        $handler = new PreflightPrintAssetHandler(
            $manager, $storage, new InspectPrintArtwork(), new RecordPrintJobActivity($manager),
        );
        $handler(new PreflightPrintAsset(42, $asset->checksum()));
        self::assertSame(PrintPreflightStatus::Passed, $report->status());
    }

    private function mockTransactions(EntityManagerInterface $manager): void
    {
        // Doctrine flushes on wrapInTransaction completion, including no-op
        // callbacks; real row locks are separately validated in MySQL CI.
        $manager->method('wrapInTransaction')->willReturnCallback(
            static function (callable $callback) use ($manager): mixed {
                $result = $callback($manager);
                $manager->flush();

                return $result;
            },
        );
    }

    private function assertSkippedWithoutSideEffects(
        PrintAsset $asset,
        PrintPreflightReport $report,
        PreflightPrintAsset $message,
    ): void {
        $manager = $this->createMock(EntityManagerInterface::class);
        $this->mockTransactions($manager);
        $repository = $this->createMock(EntityRepository::class);
        $manager->method('find')->willReturn($asset);
        $manager->method('getRepository')->willReturn($repository);
        $repository->method('findOneBy')->willReturn($report);
        $manager->expects(self::once())->method('refresh')->with($report, LockMode::PESSIMISTIC_WRITE);
        $manager->expects(self::never())->method('persist');
        $manager->expects(self::once())->method('flush');
        $storage = $this->createMock(PrintAssetStorage::class);
        $storage->expects(self::never())->method('open');

        $handler = new PreflightPrintAssetHandler(
            $manager,
            $storage,
            new InspectPrintArtwork(),
            new RecordPrintJobActivity($manager),
        );
        $handler($message);
    }

    /** @return array{PrintAsset, PrintPreflightReport, string} */
    private function fixtures(): array
    {
        $now = new \DateTimeImmutable('2026-11-09T12:00:00+00:00');
        $job = new PrintJob(new OrderItem(), 'PJ-REPLAY-TEST', 'laboprint', 'FLYER', [], $now);
        $payload = "%PDF-1.4\n1 0 obj << /Type /Page /MediaBox [0 0 400 600] >> endobj\n";
        $checksum = hash('sha256', $payload);
        $asset = new PrintAsset(
            $job,
            PrintAssetType::CustomerArtwork,
            'test.pdf',
            'ci-only/replay-test.pdf',
            'application/pdf',
            strlen($payload),
            $checksum,
            $now,
        );
        (new \ReflectionProperty(PrintAsset::class, 'id'))->setValue($asset, 42);
        $report = new PrintPreflightReport($asset, $checksum, $now);

        return [$asset, $report, $payload];
    }
}
