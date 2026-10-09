<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Caches the browser-safe state returned by Realisaprint show_variables.
 *
 * The key deliberately represents the canonical request sent to the supplier,
 * not the partial form submitted by the browser. This makes equivalent partial
 * storefront configurations share one cached state.
 */
final readonly class RealisaprintVariableStateCache
{
    public function __construct(
        private CacheInterface $cache,
        private int $timeToLive,
        private RealisaprintVariableStateLock $lock,
        private LoggerInterface $logger,
    ) {
        if ($this->timeToLive < 15) {
            throw new \InvalidArgumentException('The Realisaprint variable-state cache TTL must be at least 15 seconds.');
        }
    }

    /**
     * @param array{product: string, stock: string, variables: array<string, bool|float|int|string>, version: string, fingerprint: string} $mapped
     * @param callable(): array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>} $refresh
     *
     * @return array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>}
     */
    public function get(PrintConfiguration $configuration, array $mapped, callable $refresh): array
    {
        $key = $this->key($configuration, $mapped);
        $refreshed = false;

        $state = $this->cache->get($key, function (ItemInterface $item) use ($key, $refresh, &$refreshed): array {
            $item->expiresAfter($this->timeToLive);

            return $this->lock->synchronized($key, function () use ($key, $refresh, &$refreshed): array {
                // A process that waited for the Redis lock must reuse the value
                // written by the first process instead of calling the supplier.
                return $this->cache->get($key, function (ItemInterface $item) use ($refresh, &$refreshed): array {
                    $refreshed = true;
                    $item->expiresAfter($this->timeToLive);

                    return $refresh();
                }, 0.0);
            });
        }, 0.0);
        $this->logger->debug('Realisaprint show_variables state resolved.', [
            'cache_outcome' => $refreshed ? 'miss' : 'hit',
            'cache_key_hash' => hash('sha256', $key),
            'product_code' => $configuration->productCode(),
        ]);

        return $state;
    }

    /**
     * Stores a state already obtained from Realisaprint without overwriting a
     * value that another worker has just cached for the same canonical query.
     *
     * @param array{product: string, stock: string, variables: array<string, bool|float|int|string>, version: string, fingerprint: string} $mapped
     * @param array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>} $state
     */
    public function warm(PrintConfiguration $configuration, array $mapped, array $state): void
    {
        $this->cache->get($this->key($configuration, $mapped), function (ItemInterface $item) use ($state): array {
            $item->expiresAfter($this->timeToLive);

            return $state;
        }, 0.0);
    }

    /**
     * @param array{product: string, stock: string, variables: array<string, bool|float|int|string>, version: string, fingerprint: string} $mapped
     */
    public function key(PrintConfiguration $configuration, array $mapped): string
    {
        $variables = $mapped['variables'];
        ksort($variables, \SORT_STRING);
        $payload = [
            'product' => $mapped['product'],
            'stock' => $mapped['stock'],
            'variables' => $variables,
            'mapping_version' => $mapped['version'],
            'schema_version' => $configuration->schemaVersion(),
        ];

        return 'yoowii.realisaprint.show_variables.' . hash('sha256', json_encode($payload, \JSON_THROW_ON_ERROR));
    }
}
