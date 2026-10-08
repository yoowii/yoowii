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
        self::assertSame('expired', $this->builder()->unusableReason([
            'generated_at' => '2026-10-08T00:00:00+00:00',
        ], $this->mapping(), $this->definition(), new \DateTimeImmutable('2026-10-08T00:31:00+00:00')));
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
}
