<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Console;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'yoowii:realisaprint:redis:check', description: 'Check Redis capacity used by Realisaprint caches, locks and throttling.')]
final class CheckRealisaprintRedisCommand extends Command
{
    private const float WARNING_MEMORY_RATIO = 0.80;

    public function __construct(#[Autowire('%env(default:yoowii.realisaprint.default_redis_dsn:YOOWII_REDIS_DSN)%')] private readonly string $redisDsn)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $connection = RedisAdapter::createConnection($this->redisDsn);
            if (!$connection instanceof \Redis) {
                throw new \RuntimeException('The Redis health check requires the PHP Redis extension.');
            }
            if ('+PONG' !== $connection->ping() && true !== $connection->ping()) {
                throw new \RuntimeException('Redis did not acknowledge PING.');
            }

            $memory = $connection->info('memory');
            $stats = $connection->info('stats');
            $clients = $connection->info('clients');
            $usedMemory = (int) ($memory['used_memory'] ?? 0);
            $maxMemory = (int) ($memory['maxmemory'] ?? 0);
            $memoryRatio = $maxMemory > 0 ? $usedMemory / $maxMemory : null;
            $healthy = null !== $memoryRatio && $memoryRatio < self::WARNING_MEMORY_RATIO && 0 === (int) ($stats['rejected_connections'] ?? 0);
            $output->writeln(json_encode([
                'status' => $healthy ? 'ok' : 'warning',
                'used_memory_bytes' => $usedMemory,
                'maxmemory_bytes' => $maxMemory,
                'memory_ratio' => null === $memoryRatio ? null : round($memoryRatio, 4),
                'evicted_keys' => (int) ($stats['evicted_keys'] ?? 0),
                'rejected_connections' => (int) ($stats['rejected_connections'] ?? 0),
                'connected_clients' => (int) ($clients['connected_clients'] ?? 0),
            ], \JSON_THROW_ON_ERROR));

            return $healthy ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $exception) {
            $output->writeln(json_encode(['status' => 'error', 'message' => $exception->getMessage()], \JSON_THROW_ON_ERROR));

            return Command::FAILURE;
        }
    }
}
