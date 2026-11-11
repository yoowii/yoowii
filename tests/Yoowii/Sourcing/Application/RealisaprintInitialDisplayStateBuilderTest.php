<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintInitialDisplayStateBuilder;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use PHPUnit\Framework\TestCase;

final class RealisaprintInitialDisplayStateBuilderTest extends TestCase
{
    public function testItBuildsTheDisplayConfigurationFromPublishedDefaultsAndFixedValues(): void
    {
        $builder = (new \ReflectionClass(RealisaprintInitialDisplayStateBuilder::class))->newInstanceWithoutConstructor();
        $configuration = $builder->displayConfiguration($this->mapping(), new PersistedPrintProductDefinition('PRINT_TEST', 'v1', [
            'format' => ['type' => 'code', 'default' => 'a4', 'allowed_values' => ['a4', 'a5']],
            'reliure' => ['type' => 'code', 'default' => 'spirale', 'allowed_values' => ['agrafee', 'spirale']],
            'quantite' => ['type' => 'integer', 'minimum' => 100],
        ], ['format', 'reliure', 'quantite']));

        self::assertSame(['format' => 'a4', 'reliure' => 'agrafee', 'quantite' => 100], $configuration);
    }

    private function mapping(): SupplierProductMappingVersion
    {
        return new SupplierProductMappingVersion($this->createMock(SupplierProduct::class), 'PRINT_TEST', 'mapping-v1', [
            'realisaprint' => [
                'product' => '837',
                'stock' => '1073',
                'variables' => [
                    'VFORMAT' => ['option' => 'format', 'values' => ['a4' => 'A4', 'a5' => 'A5']],
                    'VRELIURE' => ['option' => 'reliure', 'fixed_value' => 'AGRAFEE', 'values' => ['agrafee' => 'AGRAFEE', 'spirale' => 'SPIRALE']],
                    'VQTE' => ['option' => 'quantite'],
                ],
            ],
        ], new \DateTimeImmutable('2026-10-01T00:00:00+00:00'));
    }
}
