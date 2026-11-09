<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

/** Test-environment-only message: never route on real preproduction. */
final readonly class GracefulStopProbeMessage
{
    public function __construct(public string $runId)
    {
    }
}
