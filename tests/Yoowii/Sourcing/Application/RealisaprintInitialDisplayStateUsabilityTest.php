<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Application\RealisaprintConfiguratorRefresh;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintInitialDisplayStateBuilder;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class RealisaprintInitialDisplayStateUsabilityTest extends TestCase
{
    public function testItExplainsWhyAnExpiredInitialStateIsRejected(): void
    {
        $builder = $this->builder();
        $state = $this->state($builder, '2026-10-08T00:00:00+00:00');
        $now = new \DateTimeImmutable('2026-10-08T00:31:00+00:00');

        self::assertTrue($builder->isCompatible($state, $this->mapping(), $this->definition(), $now));
        self::assertFalse($builder->isFresh($state, $now));
        self::assertFalse($builder->isUsable($state, $this->mapping(), $this->definition(), $now));
        self::assertSame('expired', $builder->unusableReason($state, $this->mapping(), $this->definition(), $now));
    }

    public function testItAcceptsAFreshCompatibleInitialState(): void
    {
        $builder = $this->builder();
        $state = $this->state($builder, '2026-10-08T00:30:00+00:00');
        $now = new \DateTimeImmutable('2026-10-08T00:31:00+00:00');

        self::assertTrue($builder->isCompatible($state, $this->mapping(), $this->definition(), $now));
        self::assertTrue($builder->isFresh($state, $now));
        self::assertTrue($builder->isUsable($state, $this->mapping(), $this->definition(), $now));
    }

    /** @dataProvider incompatibleStateProvider */
    public function testItNeverRendersAnIncompatibleState(string $key, mixed $value): void
    {
        $builder = $this->builder();
        $state = $this->state($builder, '2026-10-08T00:30:00+00:00');
        $state[$key] = $value;

        self::assertFalse($builder->isCompatible($state, $this->mapping(), $this->definition(), new \DateTimeImmutable('2026-10-08T00:31:00+00:00')));
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function incompatibleStateProvider(): iterable
    {
        yield 'mapping' => ['mapping_version', 'another-mapping'];
        yield 'schema' => ['schema_version', 'another-schema'];
        yield 'stock' => ['stock', 'another-stock'];
        yield 'fingerprint' => ['display_fingerprint', str_repeat('0', 64)];
    }

    public function testItRejectsAnInvalidProviderStateShapeBeforeRenderingIt(): void
    {
        self::assertSame('json_shape_invalid', $this->builder()->unusableReason([
            'generated_at' => '2026-10-08T00:30:00+00:00',
            'mapping_version' => 'v1',
            'schema_version' => 'v1',
            'stock' => 'stock',
            'display_configuration' => ['format' => 'a4'],
            'display_fingerprint' => 'not-reached',
            'visibility' => false,
            'availability' => [],
            'current' => [],
        ], $this->mapping(), $this->definition(), new \DateTimeImmutable('2026-10-08T00:31:00+00:00')));
    }

    private function builder(): RealisaprintInitialDisplayStateBuilder
    {
        $refresh = (new \ReflectionClass(RealisaprintConfiguratorRefresh::class))->newInstanceWithoutConstructor();

        return new RealisaprintInitialDisplayStateBuilder($refresh, new NullLogger(), 1800);
    }

    private function definition(): PersistedPrintProductDefinition
    {
        return new PersistedPrintProductDefinition('PRINT_TEST', 'v1', [
            'format' => ['type' => 'code', 'default' => 'a4', 'allowed_values' => ['a4']],
        ], ['format']);
    }

    private function mapping(): SupplierProductMappingVersion
    {
        return new SupplierProductMappingVersion($this->createMock(SupplierProduct::class), 'PRINT_TEST', 'v1', [
            'realisaprint' => ['stock' => 'stock', 'variables' => []],
        ], new \DateTimeImmutable('2026-10-01T00:00:00+00:00'));
    }

    /** @return array<string, mixed> */
    private function state(RealisaprintInitialDisplayStateBuilder $builder, string $generatedAt): array
    {
        $configuration = ['format' => 'a4'];
        $fingerprint = new \ReflectionMethod(RealisaprintInitialDisplayStateBuilder::class, 'fingerprint');

        return [
            'generated_at' => $generatedAt,
            'mapping_version' => 'v1',
            'schema_version' => 'v1',
            'stock' => 'stock',
            'display_configuration' => $configuration,
            'display_fingerprint' => $fingerprint->invoke($builder, $this->mapping(), $configuration),
            'visibility' => ['dorure_a_chaud' => false],
            'availability' => [],
            'current' => [],
        ];
    }
}
