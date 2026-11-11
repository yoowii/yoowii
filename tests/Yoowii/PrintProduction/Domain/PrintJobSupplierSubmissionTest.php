<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Domain;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use PHPUnit\Framework\TestCase;

final class PrintJobSupplierSubmissionTest extends TestCase
{
    public function testItKeepsTheIdempotencyKeyAndRecordsASimulation(): void
    {
        $at = new \DateTimeImmutable('2026-09-03T18:30:00+02:00');
        $job = new PrintJob(new OrderItem(), 'PJ-TEST-10', 'realisaprint', 'FLYER', ['schema_version' => 1], $at);
        $submission = new PrintJobSupplierSubmission($job, 'realisaprint:PJ-TEST-10', $at, $at);
        $submission->recordSimulation(['reference' => 'PJ-TEST-10'], ['simulation' => true], $at);

        self::assertSame('realisaprint:PJ-TEST-10', $submission->idempotencyKey());
        self::assertSame('simulated', $submission->status());
        self::assertSame(1, $submission->attemptCount());
        self::assertNull($submission->supplierOrderId());
    }
}
