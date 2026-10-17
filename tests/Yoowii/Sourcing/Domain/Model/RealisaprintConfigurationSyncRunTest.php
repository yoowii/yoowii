<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain\Model;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintConfigurationSyncRun;
use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncMode;
use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncStatus;
use PHPUnit\Framework\TestCase;

final class RealisaprintConfigurationSyncRunTest extends TestCase
{
    public function testItCompletesWithAnErrorSummary(): void
    {
        $run = new RealisaprintConfigurationSyncRun(RealisaprintConfigurationSyncMode::Missing, new \DateTimeImmutable());
        $run->start(new \DateTimeImmutable());
        $run->addReportEntry(['product_id' => 1, 'provider_product_id' => '42', 'name' => 'Agenda', 'outcome' => 'synchronized']);
        $run->addReportEntry(['product_id' => 2, 'provider_product_id' => '43', 'name' => 'Flyer', 'outcome' => 'failed', 'detail' => 'Unavailable']);
        $run->finish(new \DateTimeImmutable(), true);

        self::assertSame(RealisaprintConfigurationSyncStatus::CompletedWithErrors, $run->status());
        self::assertFalse($run->isActive());
        self::assertSame(['selected' => 2, 'synchronized' => 1, 'skipped' => 0, 'failed' => 1], $run->summary());
    }
}
