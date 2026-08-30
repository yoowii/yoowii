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
        $job->changeStatus(PrintJobStatus::Delivered, $now);
        $this->expectException(\DomainException::class);
        $job->changeStatus(PrintJobStatus::InProduction, $now);
    }
}
