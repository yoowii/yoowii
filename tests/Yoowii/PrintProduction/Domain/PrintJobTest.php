<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Domain;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use PHPUnit\Framework\TestCase;

final class PrintJobTest extends TestCase
{
    public function testBatCanOnlyBeApprovedWhenReady(): void
    {
        $now = new \DateTimeImmutable('2026-08-29T20:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $now);
        $this->expectException(\DomainException::class);
        $job->markBatApproved($now);
    }

    public function testDeliveredJobIsTerminal(): void
    {
        $now = new \DateTimeImmutable('2026-08-29T20:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $now);
        $job->changeStatus(PrintJobStatus::FilesReceived, $now);
        $job->changeStatus(PrintJobStatus::BatPending, $now);
        $job->changeStatus(PrintJobStatus::BatReady, $now);
        $job->changeStatus(PrintJobStatus::BatApproved, $now);
        $job->changeStatus(PrintJobStatus::InProduction, $now);
        $job->changeStatus(PrintJobStatus::Shipped, $now);
        $job->changeStatus(PrintJobStatus::Delivered, $now);
        $this->expectException(\DomainException::class);
        $job->changeStatus(PrintJobStatus::InProduction, $now);
    }

    public function testArtworkCanBeReplacedUntilBatIsReady(): void
    {
        $now = new \DateTimeImmutable('2026-08-30T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $now);

        self::assertTrue($job->canAcceptCustomerArtwork());
        $job->recordCustomerArtwork($now);
        self::assertSame(PrintJobStatus::FilesReceived, $job->status());
        self::assertTrue($job->canAcceptCustomerArtwork());

        $job->changeStatus(PrintJobStatus::BatReady, $now);
        self::assertFalse($job->canAcceptCustomerArtwork());
        self::assertTrue($job->canRegisterBat());
        $job->markBatApproved($now);
        self::assertFalse($job->canRegisterBat());
    }

    public function testGuestLinksCanBeRevoked(): void
    {
        $now = new \DateTimeImmutable('2026-08-30T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $now);

        self::assertSame(1, $job->accessVersion());
        self::assertTrue($job->guestAccessEnabled());
        $job->revokeGuestLinks($now);
        self::assertSame(2, $job->accessVersion());
        self::assertFalse($job->guestAccessEnabled());
        $job->renewGuestLinks($now);
        self::assertSame(3, $job->accessVersion());
        self::assertTrue($job->guestAccessEnabled());
    }

    public function testItRejectsAProductionStepThatSkipsClientBatApproval(): void
    {
        $now = new \DateTimeImmutable('2026-08-30T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $now);

        $this->expectException(\DomainException::class);
        $job->changeStatus(PrintJobStatus::InProduction, $now);
    }

    public function testBlockingRequiresAndKeepsAnOperationalReason(): void
    {
        $now = new \DateTimeImmutable('2026-08-30T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $now);

        try {
            $job->changeStatus(PrintJobStatus::Blocked, $now);
            self::fail('A blocking reason should be required.');
        } catch (\DomainException) {
        }

        $job->changeStatus(PrintJobStatus::Blocked, $now, 'Fichier PDF incomplet.');
        self::assertSame('Fichier PDF incomplet.', $job->statusReason());
        $job->changeStatus(PrintJobStatus::AwaitingFiles, $now);
        self::assertNull($job->statusReason());
    }

    public function testItDetectsLateNonTerminalJobs(): void
    {
        $createdAt = new \DateTimeImmutable('2026-08-30T12:00:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-1', 'laboprint', 'FLYER', ['schema_version' => 1], $createdAt);
        $job->scheduleDueAt(new \DateTimeImmutable('2026-08-31T12:00:00+02:00'), $createdAt);

        self::assertFalse($job->isLate(new \DateTimeImmutable('2026-08-31T11:59:00+02:00')));
        self::assertTrue($job->isLate(new \DateTimeImmutable('2026-08-31T12:01:00+02:00')));
    }
}
