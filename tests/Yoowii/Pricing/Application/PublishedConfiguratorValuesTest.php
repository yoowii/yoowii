<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\PublishedConfiguratorValues;
use App\Yoowii\Pricing\Application\RealisaprintConfigurationMapper;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintOptionDefinition;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintOptionType;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testItResolvesAnOmittedDynamicFieldFromThePublishedDefault(): void
    {
        self::assertSame('sans', (new PublishedConfiguratorValues())->resolve($this->definition(), [
            ['code' => 'option', 'position' => 1, 'default' => 'non'],
            ['code' => 'option_zone', 'position' => 2, 'default' => 'sans', 'depends_on' => ['option' => 'option', 'value' => 'oui']],
            ['code' => 'format', 'position' => 3, 'default' => 'a4'],
            ['code' => 'technical', 'position' => 0, 'default' => 'fixed'],
        ], ['option' => 'oui', 'format' => 'a4'])['option_zone']);
    }


    public function testItCreatesADependencyOnlyForAnUnambiguousPublishedYesNoPair(): void
    {
        $yesNo = new PersistedPrintProductDefinition('PRINT_AGENDA', 'v1', [
            'pelliculage_couverture' => ['type' => 'code', 'required' => true, 'allowed_values' => ['choice_7', 'choice_9'], 'value_labels' => ['choice_7' => 'Oui', 'choice_9' => 'Non'], 'area' => 1, 'position' => 1],
            'pelliculage_couverture_zone' => ['type' => 'code', 'required' => true, 'allowed_values' => ['sans'], 'value_labels' => ['sans' => 'Sans'], 'default' => 'sans', 'area' => 2, 'position' => 3],
        ], ['pelliculage_couverture', 'pelliculage_couverture_zone']);
        $multiChoice = new PersistedPrintProductDefinition('PRINT_FINITION', 'v1', [
            'finition' => ['type' => 'code', 'required' => true, 'allowed_values' => ['mat', 'brillant'], 'value_labels' => ['mat' => 'Mat', 'brillant' => 'Brillant'], 'area' => 1, 'position' => 1],
            'finition_zone' => ['type' => 'code', 'required' => true, 'allowed_values' => ['sans'], 'value_labels' => ['sans' => 'Sans'], 'default' => 'sans', 'area' => 2, 'position' => 2],
        ], ['finition', 'finition_zone']);

        $yesNoSchema = $yesNo->storefrontSchema();
        $multiChoiceSchema = $multiChoice->storefrontSchema();
        self::assertSame(['option' => 'pelliculage_couverture', 'value' => 'choice_7'], $yesNoSchema[1]['depends_on']);
        self::assertNull($multiChoiceSchema[1]['depends_on']);
        self::assertSame(['finition', 'finition_zone'], array_column((new PublishedConfiguratorValues())->visible($multiChoiceSchema, ['finition' => 'mat']), 'code'));
        $multiChoice->definition(); // Must remain renderable/configurable without a Yes/No pair.
    }


    public function testItKeepsTheActuallyChosenValuesInTheQuotedConfiguration(): void
    {
        $definition = new PrintProductDefinition('PRINT_AGENDA', 'v1', 'matrix_exact', [
            'pelliculage_couverture' => new PrintOptionDefinition('pelliculage_couverture', PrintOptionType::Code, true, ['choice_7', 'choice_9']),
            'pelliculage_couverture_zone' => new PrintOptionDefinition('pelliculage_couverture_zone', PrintOptionType::Code, true, ['sans', 'avec']),
        ], ['pelliculage_couverture', 'pelliculage_couverture_zone']);
        $values = (new PublishedConfiguratorValues())->resolve($definition, [
            ['code' => 'pelliculage_couverture', 'area' => 1, 'position' => 1, 'default' => 'choice_9'],
            ['code' => 'pelliculage_couverture_zone', 'area' => 2, 'position' => 1, 'default' => 'sans', 'depends_on' => ['option' => 'pelliculage_couverture', 'value' => 'choice_7']],
        ], ['pelliculage_couverture' => 'choice_7', 'pelliculage_couverture_zone' => 'avec']);

        self::assertSame(['pelliculage_couverture' => 'choice_7', 'pelliculage_couverture_zone' => 'avec'], $values);
    }


    public function testItInjectsTheCanonicalCheckboxDefaultForMissingNullAndEmptyValues(): void
    {
        $definition = new PrintProductDefinition('PRINT_DORURE', 'v1', 'matrix_exact', [
            'dorure_a_chaud' => new PrintOptionDefinition('dorure_a_chaud', PrintOptionType::Checkbox, true, ['oui', 'non']),
        ], ['dorure_a_chaud']);
        $schema = [['code' => 'dorure_a_chaud', 'type' => 'checkbox', 'position' => 1, 'default' => 'non', 'allowed_values' => ['oui', 'non'], 'values' => ['oui' => 'Oui', 'non' => 'Non']]];
        $resolver = new PublishedConfiguratorValues();

        self::assertSame('non', $resolver->resolve($definition, $schema, [])['dorure_a_chaud']);
        self::assertSame('non', $resolver->resolve($definition, $schema, ['dorure_a_chaud' => null])['dorure_a_chaud']);
        self::assertSame('non', $resolver->resolve($definition, $schema, ['dorure_a_chaud' => ''])['dorure_a_chaud']);
    }

    public function testItDoesNotReplaceAnExplicitInvalidCode(): void
    {
        $this->expectExceptionMessage('Print option "dorure_a_chaud" contains unsupported value "argent".');
        $definition = new PrintProductDefinition('PRINT_DORURE', 'v1', 'matrix_exact', [
            'dorure_a_chaud' => new PrintOptionDefinition('dorure_a_chaud', PrintOptionType::Code, true, ['sans', 'or']),
        ], ['dorure_a_chaud']);
        (new PublishedConfiguratorValues())->resolve($definition, [['code' => 'dorure_a_chaud', 'type' => 'code', 'position' => 1, 'default' => 'sans', 'allowed_values' => ['sans', 'or']]], ['dorure_a_chaud' => 'argent']);
    }

    public function testItRejectsAnInvalidPublishedCodeDefault(): void
    {
        $this->expectExceptionMessage('doit définir un défaut canonique non vide présent dans allowed_values');
        $definition = new PrintProductDefinition('PRINT_DORURE', 'v1', 'matrix_exact', [
            'dorure_a_chaud' => new PrintOptionDefinition('dorure_a_chaud', PrintOptionType::Checkbox, true, ['oui', 'non']),
        ], ['dorure_a_chaud']);
        (new PublishedConfiguratorValues())->resolve($definition, [['code' => 'dorure_a_chaud', 'type' => 'checkbox', 'position' => 1, 'default' => 'Sans', 'allowed_values' => ['oui', 'non']]], []);
    }


    public function testTheInjectedCodeIsPresentInTheMappedPayloadAndSnapshot(): void
    {
        $definition = new PrintProductDefinition('PRINT_DORURE', 'v1', 'matrix_exact', [
            'dorure_a_chaud' => new PrintOptionDefinition('dorure_a_chaud', PrintOptionType::Code, true, ['sans', 'or']),
        ], ['dorure_a_chaud']);
        $resolved = (new PublishedConfiguratorValues())->resolve($definition, [[
            'code' => 'dorure_a_chaud', 'type' => 'code', 'position' => 0, 'default' => 'sans', 'allowed_values' => ['sans', 'or'],
        ]], []);
        $configuration = $definition->configure($resolved);
        $mapped = (new RealisaprintConfigurationMapper($this->createMock(EntityManagerInterface::class)))->mapMapping($configuration, ['realisaprint' => [
            'product' => '46', 'stock' => '1', 'variables' => ['gold' => ['option' => 'dorure_a_chaud', 'values' => ['sans' => 'Sans', 'or' => 'Gold']]],
        ]], 'v1');

        self::assertSame('sans', $configuration->snapshotData()['options']['dorure_a_chaud']);
        self::assertSame('Sans', $mapped['variables']['gold']);
    }


    public function testItUsesCheckboxAsASeparateCanonicalYesNoType(): void
    {
        $definition = new PrintProductDefinition('PRINT_CHECKBOX', 'v1', 'matrix_exact', [
            'dorure_a_chaud' => new PrintOptionDefinition('dorure_a_chaud', PrintOptionType::Checkbox, true, ['oui', 'non']),
        ], ['dorure_a_chaud']);
        $schema = [['code' => 'dorure_a_chaud', 'type' => 'checkbox', 'position' => 1, 'default' => 'non', 'allowed_values' => ['oui', 'non']]];

        self::assertSame('non', (new PublishedConfiguratorValues())->resolve($definition, $schema, [])['dorure_a_chaud']);
        self::assertSame('oui', $definition->configure(['dorure_a_chaud' => true])->toArray()['dorure_a_chaud']);
        self::assertSame('non', $definition->configure(['dorure_a_chaud' => 'non'])->toArray()['dorure_a_chaud']);
    }


    public function testItResolvesOnlyAMaskedSelectFromItsPublishedDefault(): void
    {
        $definition = new PrintProductDefinition('PRINT_ZONE', 'v1', 'matrix_exact', [
            'enabled' => new PrintOptionDefinition('enabled', PrintOptionType::Checkbox, true, ['oui', 'non']),
            'enabled_zone' => new PrintOptionDefinition('enabled_zone', PrintOptionType::Code, true, ['sans', 'zone_1']),
        ], ['enabled', 'enabled_zone']);
        $resolved = (new PublishedConfiguratorValues())->resolve($definition, [
            ['code' => 'enabled', 'type' => 'checkbox', 'position' => 1, 'default' => 'non', 'allowed_values' => ['oui', 'non']],
            ['code' => 'enabled_zone', 'type' => 'code', 'position' => 2, 'default' => 'sans', 'allowed_values' => ['sans', 'zone_1'], 'depends_on' => ['option' => 'enabled', 'value' => 'oui']],
        ], ['enabled' => 'non']);

        self::assertSame('sans', $resolved['enabled_zone']);
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
