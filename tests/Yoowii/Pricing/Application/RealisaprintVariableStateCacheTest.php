<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\RealisaprintVariableStateCache;
use App\Yoowii\Pricing\Application\RealisaprintVariableStateLock;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class RealisaprintVariableStateCacheTest extends TestCase
{
    public function testItCachesEquivalentCanonicalSupplierQueries(): void
    {
        $cache = new RealisaprintVariableStateCache(new ArrayAdapter(), 3600, $this->lock());
        $configuration = new PrintConfiguration('PRINT_FLYER', 'schema-v3', ['format' => 'a5'], ['format']);
        $calls = 0;

        $first = $cache->get($configuration, $this->mapped(['VARTICLE_FORMAT' => 'A5', 'VARTICLE_PAPER' => '135']), function () use (&$calls): array {
            ++$calls;

            return $this->state();
        });
        $second = $cache->get($configuration, $this->mapped(['VARTICLE_PAPER' => '135', 'VARTICLE_FORMAT' => 'A5']), function () use (&$calls): array {
            ++$calls;

            return $this->state();
        });

        self::assertSame($first, $second);
        self::assertSame(1, $calls);
    }

    public function testItsKeyChangesWhenMappingOrSchemaChanges(): void
    {
        $cache = new RealisaprintVariableStateCache(new ArrayAdapter(), 3600, $this->lock());
        $mapped = $this->mapped(['VARTICLE_FORMAT' => 'A5']);

        $schemaV1 = new PrintConfiguration('PRINT_FLYER', 'schema-v1', ['format' => 'a5'], ['format']);
        $schemaV2 = new PrintConfiguration('PRINT_FLYER', 'schema-v2', ['format' => 'a5'], ['format']);
        $otherMapping = $mapped;
        $otherMapping['version'] = 'mapping-v2';

        self::assertNotSame($cache->key($schemaV1, $mapped), $cache->key($schemaV2, $mapped));
        self::assertNotSame($cache->key($schemaV1, $mapped), $cache->key($schemaV1, $otherMapping));
    }

    public function testItUsesTheCanonicalCacheKeyAsTheDistributedLockResource(): void
    {
        $lock = new class implements RealisaprintVariableStateLock {
            /** @var list<string> */
            public array $resources = [];

            public function synchronized(string $resource, callable $callback): mixed
            {
                $this->resources[] = $resource;

                return $callback();
            }
        };
        $cache = new RealisaprintVariableStateCache(new ArrayAdapter(), 3600, $lock);
        $configuration = new PrintConfiguration('PRINT_FLYER', 'schema-v3', ['format' => 'a5'], ['format']);
        $mapped = $this->mapped(['VARTICLE_FORMAT' => 'A5']);

        $cache->get($configuration, $mapped, fn (): array => $this->state());

        self::assertSame([$cache->key($configuration, $mapped)], $lock->resources);
    }

    /** @param array<string, bool|float|int|string> $variables
     * @return array{product: string, stock: string, variables: array<string, bool|float|int|string>, version: string, fingerprint: string}
     */
    private function mapped(array $variables): array
    {
        return [
            'product' => 'flyer',
            'stock' => 'standard',
            'variables' => $variables,
            'version' => 'mapping-v1',
            'fingerprint' => 'unused-by-variable-state-cache',
        ];
    }

    /** @return array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>} */
    private function state(): array
    {
        return [
            'visibility' => ['format' => true],
            'availability' => ['format' => ['a5']],
            'current' => [],
            'alerts' => [],
            'infos' => [],
        ];
    }

    private function lock(): RealisaprintVariableStateLock
    {
        return new class implements RealisaprintVariableStateLock {
            public function synchronized(string $resource, callable $callback): mixed
            {
                return $callback();
            }
        };
    }
}
