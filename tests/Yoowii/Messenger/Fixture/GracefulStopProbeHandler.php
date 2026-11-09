<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Messenger\Fixture;

/**
 * CI-only controlled in-flight work. No external calls, emails or database writes.
 */
final class GracefulStopProbeHandler
{
    public function __invoke(GracefulStopProbeMessage $message): void
    {
        if (!preg_match('/^[a-f0-9]{16}$/D', $message->runId)) {
            throw new \InvalidArgumentException('Invalid CI probe identifier');
        }

        $base = sys_get_temp_dir() . '/yoowii-ci-graceful-' . $message->runId;
        if (file_put_contents($base . '.started', '1') === false) {
            throw new \RuntimeException('Cannot mark CI probe as started');
        }

        // A real worker should finish this in-flight handler when sent SIGTERM.
        // No side effects other than temporary marker files in the CI runner.
        usleep(2000000);

        if (file_put_contents($base . '.finished', '1') === false) {
            throw new \RuntimeException('Cannot mark CI probe as finished');
        }
    }
}
