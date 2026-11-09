<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

/**
 * CI-only payload: must never be routed or dispatched outside APP_ENV=test.
 */
final readonly class SafeProbeMessage
{
    public function __construct(public string $runId)
    {
    }
}
