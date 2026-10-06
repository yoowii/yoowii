<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintValidationPreview;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use PHPUnit\Framework\TestCase;

final class RealisaprintValidationPreviewTest extends TestCase
{
    public function testItShowsTheQuotedSampleAndCoverageForCodedAndFreeValues(): void
    {
        $preview = (new RealisaprintValidationPreview())->build(
            $this->definition(),
            $this->mapping(['standard' => '0', 'express' => '2']),
            $this->catalog(),
            ['delai' => 'express', 'quantite' => 50],
        );

        self::assertTrue($preview['covered']);
        self::assertSame('837', $preview['stock']);
        self::assertSame('Express', $preview['rows'][0]['sample']);
        self::assertSame('2', $preview['rows'][0]['provider_value']);
        self::assertSame('Standard', $preview['rows'][0]['default']);
        self::assertSame('0', $preview['rows'][0]['provider_default_value']);
        self::assertSame(2, $preview['rows'][0]['mapped']);
        self::assertSame('50', $preview['rows'][1]['provider_value']);
        self::assertTrue($preview['rows'][1]['covered']);
    }

    public function testItFlagsAProviderValueRemovedAfterSynchronization(): void
    {
        $catalog = $this->catalog();
        unset($catalog['variables']['VARTICLE_28524_']['values'][2]);
        $preview = (new RealisaprintValidationPreview())->build(
            $this->definition(),
            $this->mapping(['standard' => '0', 'express' => '2']),
            $catalog,
            ['delai' => 'express', 'quantite' => 50],
        );

        self::assertFalse($preview['covered']);
        self::assertFalse($preview['rows'][0]['covered']);
        self::assertSame(1, $preview['rows'][0]['mapped']);
    }

    private function definition(): PersistedPrintProductDefinition
    {
        return new PersistedPrintProductDefinition('PRINT_AGENDA', 'v1', [
            'delai' => ['type' => 'code', 'allowed_values' => ['standard', 'express'], 'label' => 'Délai', 'value_labels' => ['standard' => 'Standard', 'express' => 'Express'], 'default' => 'standard', 'provider_variable' => 'VARTICLE_28524_'],
            'quantite' => ['type' => 'integer', 'allowed_values' => [], 'minimum' => 1, 'provider_variable' => 'VARTICLE_28514_'],
        ], ['delai', 'quantite']);
    }

    /** @param array<string, string> $codes */
    private function mapping(array $codes): SupplierProductMappingVersion
    {
        return new SupplierProductMappingVersion(
            $this->createMock(SupplierProduct::class),
            'PRINT_AGENDA',
            'v1',
            ['realisaprint' => ['product' => '123', 'stock' => '837', 'variables' => [
                'VARTICLE_28524_' => ['option' => 'delai', 'values' => $codes],
                'VARTICLE_28514_' => ['option' => 'quantite', 'values' => []],
            ]]],
            new \DateTimeImmutable('2026-10-04T08:00:00+00:00'),
        );
    }

    /** @return array<string, mixed> */
    private function catalog(): array
    {
        return [
            'stocks' => ['837' => 'Agenda'],
            'variables' => [
                'VARTICLE_28524_' => ['name' => 'Délai', 'values' => ['0' => 'Standard', '2' => 'Express'], 'default' => '0'],
                'VARTICLE_28514_' => ['name' => 'Quantité', 'values' => false],
            ],
        ];
    }
}
