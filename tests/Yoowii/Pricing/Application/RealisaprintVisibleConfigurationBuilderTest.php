<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\RealisaprintVisibleConfigurationBuilder;
use App\Yoowii\Pricing\Application\RealisaprintConfigurationMapper;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RealisaprintVisibleConfigurationBuilderTest extends TestCase
{
    public function testItExcludesHiddenOptionsAndKeepsOptionsMissingFromVisibility(): void
    {
        $configuration = (new RealisaprintVisibleConfigurationBuilder())->buildVisibleProviderConfiguration(
            new PrintConfiguration('PRINT_TEST', 'v1', ['format' => 'a4', 'dorure_a_chaud' => 'or', 'quantite' => 100], ['format', 'dorure_a_chaud', 'quantite']),
            ['visibility' => ['dorure_a_chaud' => false], 'availability' => ['format' => ['a4'], 'quantite' => ['100']]],
        );

        self::assertSame(['format' => 'a4', 'quantite' => 100], $configuration->toArray());
        self::assertSame(['format', 'quantite'], $configuration->pricingAxes());
        $payload = (new RealisaprintConfigurationMapper($this->createMock(EntityManagerInterface::class)))->mapMapping($configuration, [
            'realisaprint' => ['product' => 'product', 'stock' => 'stock', 'variables' => [
                'FORMAT' => ['option' => 'format', 'values' => ['a4' => 'A4']],
                'DORURE' => ['option' => 'dorure_a_chaud', 'fixed_value' => 'GOLD'],
                'QTE' => ['option' => 'quantite', 'values' => []],
            ]],
        ], 'v1');

        self::assertSame(['FORMAT' => 'A4', 'QTE' => 100], $payload['variables']);
    }

    public function testItRejectsAnIncompleteVisiblePricingAxis(): void
    {
        $this->expectExceptionMessage('Renseignez le champ requis « quantite »');

        (new RealisaprintVisibleConfigurationBuilder())->buildVisibleProviderConfiguration(
            new PrintConfiguration('PRINT_TEST', 'v1', ['format' => 'a4'], ['format', 'quantite']),
            ['visibility' => ['quantite' => true]],
        );
    }

    public function testItMapsOnlyExplicitStorefrontChoicesForShowVariables(): void
    {
        $payload = (new RealisaprintConfigurationMapper($this->createMock(EntityManagerInterface::class)))->mapMapping(
            new PrintConfiguration('PRINT_TEST', 'v1', ['format' => 'a4', 'quantite' => 100], ['format', 'quantite']),
            ['realisaprint' => ['product' => 'product', 'stock' => 'stock', 'variables' => [
                'FORMAT' => ['option' => 'format', 'values' => ['a4' => 'A4']],
                'QTE' => ['option' => 'quantite', 'values' => []],
            ]]],
            'v1',
            ['format' => 'a4'],
        );

        self::assertSame(['FORMAT' => 'A4'], $payload['variables']);
    }
}
