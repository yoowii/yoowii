<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

/** CI-only, no business or supplier effects. */
final readonly class ForcedStopProbeMessage
{
    public function __construct(public string $runId)
    {
    }
}
