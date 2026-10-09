<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Application\RealisaprintRequestThrottle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Globally spaces calls to one Realisaprint endpoint across all PHP workers.
 */
final class RedisRealisaprintRequestThrottle implements RealisaprintRequestThrottle
{
    private const int MINIMUM_INTERVAL_MILLISECONDS = 15_000;
    private const int MAXIMUM_WAIT_MILLISECONDS = 90_000;
    private const int MAXIMUM_SLEEP_MILLISECONDS = 1_000;

    private ?\Redis $connection = null;

    public function __construct(private readonly string $redisDsn, private readonly LoggerInterface $logger)
    {
    }

    public function acquire(string $operation): void
    {
        $key = 'yoowii.realisaprint.rate_limit.' . hash('sha256', $operation);
        $deadline = (microtime(true) * 1000) + self::MAXIMUM_WAIT_MILLISECONDS;
        $waitedMilliseconds = 0;

        do {
            $remaining = (int) $this->redis()->eval(<<<'LUA'
                local ttl = redis.call('pttl', KEYS[1])
                if ttl <= 0 then
                    redis.call('psetex', KEYS[1], ARGV[1], 'reserved')

                    return 0
                end

                return ttl
                LUA, [$key, (string) self::MINIMUM_INTERVAL_MILLISECONDS], 1);
            if (0 === $remaining) {
                if (0 < $waitedMilliseconds) {
                    $this->logger->info('Realisaprint request waited for a rate-limit slot.', [
                        'operation' => $operation,
                        'waited_milliseconds' => $waitedMilliseconds,
                    ]);
                }

                return;
            }

            $sleepMilliseconds = min($remaining, self::MAXIMUM_SLEEP_MILLISECONDS);
            $waitedMilliseconds += $sleepMilliseconds;
            usleep($sleepMilliseconds * 1_000);
        } while ((microtime(true) * 1000) < $deadline);

        throw new \RuntimeException(sprintf('Realisaprint rate limit: timed out while waiting for "%s".', $operation));
    }

    private function redis(): \Redis
    {
        if (null !== $this->connection) {
            return $this->connection;
        }

        $connection = RedisAdapter::createConnection($this->redisDsn);
        if (!$connection instanceof \Redis) {
            throw new \RuntimeException('The Realisaprint request throttle requires the PHP Redis extension.');
        }

        return $this->connection = $connection;
    }
}
