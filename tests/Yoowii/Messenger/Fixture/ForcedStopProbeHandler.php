<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

/**
 * Records test-only attempt markers in /tmp to allow a forced worker death
 * before ACK and a subsequent simulated Doctrine redelivery.
 */
final class ForcedStopProbeHandler
{
    public function __invoke(ForcedStopProbeMessage $message): void
    {
        if (!preg_match('/^[a-f0-9]{16}$/D', $message->runId)) {
            throw new \InvalidArgumentException('Invalid CI probe ID');
        }

        $base = sys_get_temp_dir() . '/yoowii-ci-forced-' . $message->runId;
        // Only the CI workspace is ever touched. Attempt counts make the
        // at-least-once behavior visible; no business side effects occur.
        $count = file_exists($base . '.attempts')
            ? (int) file_get_contents($base . '.attempts') + 1
            : 1;
        if (file_put_contents($base . '.attempts', (string) $count) === false) {
            throw new \RuntimeException('Cannot write probe attempt marker');
        }

        if ($count === 1) {
            if (file_put_contents($base . '.started', '1') === false) {
                throw new \RuntimeException('Cannot mark first attempt');
            }
            // Test sends SIGKILL while this first attempt is in flight.
            usleep(8000000);
        }

        if (file_put_contents($base . '.finished', '1') === false) {
            throw new \RuntimeException('Cannot mark completed attempt');
        }
    }
}
