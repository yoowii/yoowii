<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Infrastructure\Cache;

use App\Yoowii\Pricing\Application\RealisaprintVariableStateLock;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Redis SET NX lock with a token-checked Lua release.
 *
 * A waiting process always rechecks the cache after it gets the lock, which
 * lets it reuse the value stored by the process that held the lock first.
 */
final class RedisRealisaprintVariableStateLock implements RealisaprintVariableStateLock
{
    private const int LOCK_TTL_MILLISECONDS = 180_000;
    private const int WAIT_TIMEOUT_MILLISECONDS = 210_000;
    private const int RETRY_DELAY_MICROSECONDS = 100_000;

    private ?\Redis $connection = null;

    public function __construct(private readonly string $redisDsn)
    {
    }

    public function synchronized(string $resource, callable $callback): mixed
    {
        $key = 'yoowii.realisaprint.show_variables.lock.' . hash('sha256', $resource);
        $token = bin2hex(random_bytes(16));
        $deadline = (microtime(true) * 1000) + self::WAIT_TIMEOUT_MILLISECONDS;

        while ((microtime(true) * 1000) < $deadline) {
            if ($this->redis()->set($key, $token, ['nx', 'px' => self::LOCK_TTL_MILLISECONDS])) {
                try {
                    return $callback();
                } finally {
                    $this->release($key, $token);
                }
            }

            usleep(self::RETRY_DELAY_MICROSECONDS);
        }

        throw new \RuntimeException('Timed out while waiting for the Realisaprint variable-state lock.');
    }

    private function redis(): \Redis
    {
        if (null !== $this->connection) {
            return $this->connection;
        }

        $connection = RedisAdapter::createConnection($this->redisDsn);
        if (!$connection instanceof \Redis) {
            throw new \RuntimeException('The Realisaprint distributed lock requires the PHP Redis extension.');
        }

        return $this->connection = $connection;
    }

    private function release(string $key, string $token): void
    {
        $this->redis()->eval(<<<'LUA'
            if redis.call('get', KEYS[1]) == ARGV[1] then
                return redis.call('del', KEYS[1])
            end

            return 0
            LUA, [$key, $token], 1);
    }
}
