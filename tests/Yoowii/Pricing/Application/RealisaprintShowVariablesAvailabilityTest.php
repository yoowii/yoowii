<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\RealisaprintAvailabilityFallback;
use App\Yoowii\Pricing\Application\RealisaprintConfigurationMapper;
use App\Yoowii\Pricing\Application\RealisaprintConfiguratorRefresh;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RealisaprintShowVariablesAvailabilityTest extends TestCase
{
    public function testItNormalizesAvailabilityMarkersToCanonicalCodes(): void
    {
        $refresh = (new \ReflectionClass(RealisaprintConfiguratorRefresh::class))->newInstanceWithoutConstructor();
        $state = $refresh->normalizeResponse([
            'VARTICLE_SUPPORT_' => true,
            'variable_values' => [
                'VARTICLE_SUPPORT_' => [
                    'provider-115-mat' => '115g/m² couché mat',
                    'provider-115-gloss' => '115g/m² couché brillant',
                    'provider-135-gloss' => '135g/m² couché brillant',
                    'provider-170-mat' => '170g/m² couché mat',
                ],
            ],
            'current_values' => ['VARTICLE_SUPPORT_' => 'provider-135-gloss'],
        ], $this->mapping());

        self::assertSame([
            '115g_m2_couche_mat',
            '115g_m2_couche_brillant',
            '135g_m2_couche_brillant',
            '170g_m2_couche_mat',
        ], $state['availability']['support_interieur']);
        self::assertSame('135g_m2_couche_brillant', $state['current']['support_interieur']);
        self::assertArrayNotHasKey('values', $state);
    }

    public function testItNormalizesNumericVisibilityMarkers(): void
    {
        $refresh = (new \ReflectionClass(RealisaprintConfiguratorRefresh::class))->newInstanceWithoutConstructor();

        self::assertTrue($refresh->normalizeResponse([
            'VARTICLE_SUPPORT_' => '1',
        ], $this->mapping())['visibility']['support_interieur']);
        self::assertFalse($refresh->normalizeResponse([
            'VARTICLE_SUPPORT_' => 0,
        ], $this->mapping())['visibility']['support_interieur']);
    }

    public function testItNormalizesNestedProviderVisibilityToTheCanonicalOptionCode(): void
    {
        $refresh = (new \ReflectionClass(RealisaprintConfiguratorRefresh::class))->newInstanceWithoutConstructor();

        self::assertFalse($refresh->normalizeResponse([
            'visibility' => ['VARTICLE_SUPPORT_' => 0],
        ], $this->mapping())['visibility']['support_interieur']);
    }

    public function testItDoesNotExposeAnAmbiguousSupplierCurrentValue(): void
    {
        $refresh = (new \ReflectionClass(RealisaprintConfiguratorRefresh::class))->newInstanceWithoutConstructor();
        $mapping = new SupplierProductMappingVersion($this->createMock(SupplierProduct::class), 'PRINT_TEST', 'v2', [
            'realisaprint' => ['product' => 'product', 'stock' => 'stock', 'variables' => [
                'VARTICLE_SUPPORT_' => ['option' => 'support_interieur', 'values' => ['mat' => 'provider-paper', 'brillant' => 'provider-paper']],
            ]],
        ], new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        self::assertSame([], $refresh->normalizeResponse(['current_values' => ['VARTICLE_SUPPORT_' => 'provider-paper']], $mapping)['current']);
    }

    public function testItConvertsProviderZeroOneValueKeysToCanonicalCodes(): void
    {
        $refresh = (new \ReflectionClass(RealisaprintConfiguratorRefresh::class))->newInstanceWithoutConstructor();
        $mapping = new SupplierProductMappingVersion($this->createMock(SupplierProduct::class), 'PRINT_TEST', 'v3', [
            'realisaprint' => ['product' => 'product', 'stock' => 'stock', 'variables' => [
                'VOPTIONS' => ['option' => 'pelliculage', 'values' => ['mat' => '1', 'brillant' => '0']],
            ]],
        ], new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $availability = $refresh->normalizeResponse(['variable_values' => ['VOPTIONS' => ['1' => 'Mat']]], $mapping)['availability'];

        self::assertSame(['mat'], $availability['pelliculage']);
        self::assertNotContains('1', $availability['pelliculage']);
    }

    public function testItFallsBackToThePublishedDefaultThenMapsThatCanonicalValue(): void
    {
        $definition = $this->definition('115g_m2_couche_mat');
        $values = (new RealisaprintAvailabilityFallback())->apply([
            'support_interieur' => '90g_m2_couche_brillant',
        ], [
            'availability' => ['support_interieur' => ['115g_m2_couche_mat', '135g_m2_couche_brillant']],
            'current' => [],
        ], $definition);

        self::assertSame('115g_m2_couche_mat', $values['support_interieur']);
        $payload = (new RealisaprintConfigurationMapper($this->createMock(EntityManagerInterface::class)))->mapMapping(
            new PrintConfiguration('PRINT_TEST', 'v1', $values, ['support_interieur']),
            $this->mapping()->configurationMapping(),
            'v1',
        );
        self::assertSame('provider-115-mat', $payload['variables']['VARTICLE_SUPPORT_']);
    }

    public function testItFallsBackToTheFirstPublishedAvailableChoiceWhenDefaultIsUnavailable(): void
    {
        $values = (new RealisaprintAvailabilityFallback())->apply([
            'support_interieur' => '90g_m2_couche_brillant',
        ], [
            'availability' => ['support_interieur' => ['135g_m2_couche_brillant']],
            'current' => [],
        ], $this->definition('90g_m2_couche_brillant'));

        self::assertSame('135g_m2_couche_brillant', $values['support_interieur']);
    }

    public function testItExplainsWhenNoCanonicalChoiceRemainsAvailable(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('La configuration a changé chez le fournisseur ; choisissez une nouvelle valeur pour « Support intérieur ».');

        (new RealisaprintAvailabilityFallback())->apply([
            'support_interieur' => '90g_m2_couche_brillant',
        ], [
            'availability' => ['support_interieur' => []],
            'current' => [],
        ], $this->definition('90g_m2_couche_brillant'));
    }

    private function definition(string $default): PersistedPrintProductDefinition
    {
        return new PersistedPrintProductDefinition('PRINT_TEST', 'v1', [
            'support_interieur' => [
                'type' => 'code',
                'label' => 'Support intérieur',
                'default' => $default,
                'allowed_values' => ['90g_m2_couche_brillant', '115g_m2_couche_mat', '135g_m2_couche_brillant', '170g_m2_couche_mat'],
            ],
        ], ['support_interieur']);
    }

    private function mapping(): SupplierProductMappingVersion
    {
        return new SupplierProductMappingVersion($this->createMock(SupplierProduct::class), 'PRINT_TEST', 'v1', [
            'realisaprint' => [
                'product' => 'product',
                'stock' => 'stock',
                'variables' => [
                    'VARTICLE_SUPPORT_' => [
                        'option' => 'support_interieur',
                        'values' => [
                            '90g_m2_couche_brillant' => 'provider-90-gloss',
                            '115g_m2_couche_mat' => 'provider-115-mat',
                            '115g_m2_couche_brillant' => 'provider-115-gloss',
                            '135g_m2_couche_brillant' => 'provider-135-gloss',
                            '170g_m2_couche_mat' => 'provider-170-mat',
                        ],
                    ],
                ],
            ],
        ], new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    }
}
