<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\PublishedConfiguratorValues;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintOptionDefinition;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintOptionType;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;
use PHPUnit\Framework\TestCase;

final class PublishedConfiguratorValuesTest extends TestCase
{
    public function testItOrdersAreasAndResolvesAnInactiveZoneWithItsPublishedDefault(): void
    {
        $schemas = [
            ['code' => 'format', 'area' => 2, 'position' => 2, 'default' => 'a4'],
            ['code' => 'option_zone', 'area' => 2, 'position' => 3, 'default' => 'sans', 'depends_on' => ['option' => 'option', 'value' => 'oui']],
            ['code' => 'option', 'area' => 1, 'position' => 1, 'default' => 'non'],
            ['code' => 'technical', 'area' => 1, 'position' => 0, 'default' => 'fixed'],
        ];
        $definition = $this->definition();
        $resolver = new PublishedConfiguratorValues();

        self::assertSame(['option', 'format'], array_column($resolver->visible($schemas, ['option' => 'non']), 'code'));
        self::assertSame(['option', 'format', 'option_zone'], array_column($resolver->visible($schemas, ['option' => 'oui']), 'code'));
        self::assertSame(['option' => 'non', 'option_zone' => 'sans', 'format' => 'a4', 'technical' => 'fixed'], $resolver->resolve($definition, $schemas, ['option' => 'non', 'format' => 'a4']));
    }

    public function testItRequiresTheZoneAgainWhenTheParentIsYes(): void
    {
        $this->expectExceptionMessage('Required print option "option_zone" is missing.');
        (new PublishedConfiguratorValues())->resolve($this->definition(), [
            ['code' => 'option', 'position' => 1, 'default' => 'non'],
            ['code' => 'option_zone', 'position' => 2, 'default' => 'sans', 'depends_on' => ['option' => 'option', 'value' => 'oui']],
            ['code' => 'format', 'position' => 3, 'default' => 'a4'],
            ['code' => 'technical', 'position' => 0, 'default' => 'fixed'],
        ], ['option' => 'oui', 'format' => 'a4']);
    }

    private function definition(): PrintProductDefinition
    {
        return new PrintProductDefinition('PRINT_AGENDA', 'v1', 'matrix_exact', [
            'option' => new PrintOptionDefinition('option', PrintOptionType::Code, true, ['oui', 'non']),
            'option_zone' => new PrintOptionDefinition('option_zone', PrintOptionType::Code, true, ['sans', 'avec']),
            'format' => new PrintOptionDefinition('format', PrintOptionType::Code, true, ['a4']),
            'technical' => new PrintOptionDefinition('technical', PrintOptionType::Code, true, ['fixed']),
        ], ['option', 'option_zone', 'format', 'technical']);
    }
}
