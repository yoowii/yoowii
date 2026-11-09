<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

/**
 * Deliberately no-op. Tests the transport/worker ACK, not business effects.
 */
final class SafeProbeHandler
{
    public function __invoke(SafeProbeMessage $message): void
    {
        // No writes, API calls, mail, or supplier side effects.
    }
}
