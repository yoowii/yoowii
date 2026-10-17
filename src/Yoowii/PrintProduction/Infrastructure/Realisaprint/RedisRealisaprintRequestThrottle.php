<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Application\RealisaprintRequestThrottle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Globally serializes every Realisaprint API call across all PHP workers.
 */
final class RedisRealisaprintRequestThrottle implements RealisaprintRequestThrottle
{
    // Realisaprint documents 15 seconds; keep a two-second safety margin.
    private const int MINIMUM_INTERVAL_MILLISECONDS = 17_000;

    private const int MAXIMUM_WAIT_MILLISECONDS = 90_000;

    private const int MAXIMUM_SLEEP_MILLISECONDS = 1_000;

    private const int IN_FLIGHT_TTL_MILLISECONDS = 120_000;

    private ?\Redis $connection = null;

    public function __construct(private readonly string $redisDsn, private readonly LoggerInterface $logger)
    {
    }

    public function acquire(): string
    {
        $key = 'yoowii.realisaprint.rate_limit.global';
        $lease = bin2hex(random_bytes(16));
        $deadline = (microtime(true) * 1000) + self::MAXIMUM_WAIT_MILLISECONDS;
        $waitedMilliseconds = 0;

        do {
            $remaining = $this->redis()->eval(<<<'LUA'
                local ttl = redis.call('pttl', KEYS[1])
                if ttl <= 0 then
                    redis.call('psetex', KEYS[1], ARGV[1], ARGV[2])

                    return 0
                end

                return ttl
                LUA, [$key, (string) self::IN_FLIGHT_TTL_MILLISECONDS, $lease], 1);
            $remaining = is_int($remaining) ? $remaining : 1_000;
            if (0 === $remaining) {
                if (0 < $waitedMilliseconds) {
                    $this->logger->info('Realisaprint request waited for a rate-limit slot.', [
                        'waited_milliseconds' => $waitedMilliseconds,
                    ]);
                }

                return $lease;
            }

            $sleepMilliseconds = min($remaining, self::MAXIMUM_SLEEP_MILLISECONDS);
            $waitedMilliseconds += $sleepMilliseconds;
            usleep($sleepMilliseconds * 1_000);
        } while ((microtime(true) * 1000) < $deadline);

        throw new \RuntimeException('Realisaprint rate limit: timed out while waiting for the global API slot.');
    }

    public function complete(string $lease): void
    {
        $this->redis()->eval(<<<'LUA'
            if redis.call('get', KEYS[1]) == ARGV[2] then
                redis.call('pexpire', KEYS[1], ARGV[1])
            end
            LUA, ['yoowii.realisaprint.rate_limit.global', (string) self::MINIMUM_INTERVAL_MILLISECONDS, $lease], 1);
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
