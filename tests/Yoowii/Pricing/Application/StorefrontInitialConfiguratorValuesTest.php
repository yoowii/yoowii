<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\StorefrontInitialConfiguratorValues;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintOptionDefinition;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintOptionType;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;
use PHPUnit\Framework\TestCase;

final class StorefrontInitialConfiguratorValuesTest extends TestCase
{
    public function testItNeverUsesSupplierDefaultsForMultipleChoicesOrFreeFields(): void
    {
        $values = (new StorefrontInitialConfiguratorValues())->resolve($this->definition(), [
            ['code' => 'format', 'type' => 'code', 'allowed_values' => ['a4', 'a5'], 'default' => 'a4'],
            ['code' => 'quantite', 'type' => 'integer', 'default' => 500],
            ['code' => 'largeur', 'type' => 'float', 'default' => 210],
            ['code' => 'reference', 'type' => 'text', 'default' => 'Technique'],
        ], [], ['display_configuration' => ['format' => 'a4', 'quantite' => 500, 'largeur' => 210, 'reference' => 'Technique']]);

        self::assertSame([], $values);
    }

    public function testItSelectsAvailableNoAndTheOnlyActuallyAvailableValue(): void
    {
        $values = (new StorefrontInitialConfiguratorValues())->resolve($this->definition(), [
            ['code' => 'dorure_a_chaud', 'type' => 'checkbox', 'allowed_values' => ['oui', 'non'], 'default' => 'oui'],
            ['code' => 'format', 'type' => 'code', 'allowed_values' => ['a4', 'a5']],
        ], [], ['availability' => ['dorure_a_chaud' => ['oui', 'non'], 'format' => ['a5']]]);

        self::assertSame(['dorure_a_chaud' => 'non', 'format' => 'a5'], $values);
    }

    public function testItRecognizesTheCanonicalNonChoiceFromItsPublishedLabel(): void
    {
        $values = (new StorefrontInitialConfiguratorValues())->resolve($this->definition(), [
            ['code' => 'dorure_a_chaud', 'type' => 'checkbox', 'allowed_values' => ['choice_7', 'choice_9'], 'values' => ['choice_7' => 'Oui', 'choice_9' => 'Non']],
        ], [], null);

        self::assertSame(['dorure_a_chaud' => 'choice_9'], $values);
    }

    public function testItDoesNotPrefillAHiddenOptionAndExcludesFixedValuesFromEditableData(): void
    {
        $values = (new StorefrontInitialConfiguratorValues())->resolve($this->definition(), [
            ['code' => 'format', 'type' => 'code', 'allowed_values' => ['a4']],
            ['code' => 'sides', 'type' => 'code', 'allowed_values' => ['recto']],
        ], ['sides' => ['value' => 'recto', 'label' => 'Recto']], ['visibility' => ['format' => false]]);

        self::assertSame([], $values);
    }

    private function definition(): PrintProductDefinition
    {
        return new PrintProductDefinition('PRINT_AGENDA', 'v1', 'matrix_exact', [
            'format' => new PrintOptionDefinition('format', PrintOptionType::Code, true, ['a4', 'a5']),
            'dorure_a_chaud' => new PrintOptionDefinition('dorure_a_chaud', PrintOptionType::Checkbox, true, ['oui', 'non']),
            'quantite' => new PrintOptionDefinition('quantite', PrintOptionType::Integer, true, [], 1),
            'largeur' => new PrintOptionDefinition('largeur', PrintOptionType::Float, true, [], 1),
            'reference' => new PrintOptionDefinition('reference', PrintOptionType::Text, true),
            'sides' => new PrintOptionDefinition('sides', PrintOptionType::Code, true, ['recto']),
        ], ['format', 'dorure_a_chaud', 'quantite', 'largeur', 'reference', 'sides']);
    }
}
