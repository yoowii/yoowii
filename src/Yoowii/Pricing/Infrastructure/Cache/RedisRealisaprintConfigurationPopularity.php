<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Infrastructure\Cache;

use App\Yoowii\Pricing\Application\RealisaprintConfigurationPopularity;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

/**
 * Keeps a bounded popularity ranking of opaque configuration identifiers.
 */
final class RedisRealisaprintConfigurationPopularity implements RealisaprintConfigurationPopularity
{
    private const string KEY = 'yoowii.realisaprint.configuration_popularity';
    private const int MAXIMUM_STORED_CONFIGURATIONS = 1_000;

    private ?\Redis $connection = null;

    public function __construct(private readonly string $redisDsn, private readonly LoggerInterface $logger)
    {
    }

    public function record(string $configurationFingerprint, string $mappingVersion): void
    {
        try {
            $member = hash('sha256', $configurationFingerprint . '|' . $mappingVersion);
            $this->redis()->zIncrBy(self::KEY, 1, $member);
            $this->redis()->zRemRangeByRank(self::KEY, 0, -self::MAXIMUM_STORED_CONFIGURATIONS - 1);
        } catch (\Throwable $exception) {
            // Popularity is only a warmup signal and must never block a quote.
            $this->logger->warning('Realisaprint configuration popularity could not be recorded.', [
                'exception_class' => $exception::class,
            ]);
        }
    }

    private function redis(): \Redis
    {
        if (null !== $this->connection) {
            return $this->connection;
        }

        $connection = RedisAdapter::createConnection($this->redisDsn);
        if (!$connection instanceof \Redis) {
            throw new \RuntimeException('The Realisaprint popularity ranking requires the PHP Redis extension.');
        }

        return $this->connection = $connection;
    }
}
