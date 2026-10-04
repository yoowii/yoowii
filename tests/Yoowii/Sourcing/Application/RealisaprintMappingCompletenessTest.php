<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintMappingCompleteness;
use PHPUnit\Framework\TestCase;

final class RealisaprintMappingCompletenessTest extends TestCase
{
    public function testItAcceptsProviderCodesAndAFreeNumericQuantity(): void
    {
        (new RealisaprintMappingCompleteness())->assertComplete($this->definition(), $this->configuration(), [
            'stock' => '837',
            'variables' => [
                'VARTICLE_28524_' => ['option' => 'delai', 'values' => ['standard' => '0', 'urgence' => '1', 'express' => '2']],
                'VARTICLE_28541_' => ['option' => 'format_ferme', 'values' => ['royal_16_x_24cm' => '1', 'a5_14_8_x_21cm' => '2', 'a4_21_x_29_7cm' => '3']],
                'VARTICLE_28514_' => ['option' => 'quantite_du_meme_visuel', 'values' => []],
            ],
        ]);

        self::assertTrue(true);
    }

    public function testItRejectsAnIncompleteMapping(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('exactement toutes les variables');

        (new RealisaprintMappingCompleteness())->assertComplete($this->definition(), $this->configuration(), [
            'stock' => '837',
            'variables' => ['VARTICLE_28524_' => ['option' => 'delai', 'values' => ['standard' => '0', 'urgence' => '1', 'express' => '2']]],
        ]);
    }

    private function definition(): PersistedPrintProductDefinition
    {
        return new PersistedPrintProductDefinition('PRINT_AGENDA', 'v1', [
            'delai' => ['type' => 'code', 'allowed_values' => ['standard', 'urgence', 'express'], 'provider_variable' => 'VARTICLE_28524_'],
            'format_ferme' => ['type' => 'code', 'allowed_values' => ['royal_16_x_24cm', 'a5_14_8_x_21cm', 'a4_21_x_29_7cm'], 'provider_variable' => 'VARTICLE_28541_'],
            'quantite_du_meme_visuel' => ['type' => 'integer', 'allowed_values' => [], 'minimum' => 1, 'provider_variable' => 'VARTICLE_28514_'],
        ], ['delai', 'format_ferme', 'quantite_du_meme_visuel']);
    }

    /** @return array<string, mixed> */
    private function configuration(): array
    {
        return [
            'stocks' => ['837' => 'Agenda', '1228' => 'Agenda semainier'],
            'variables' => [
                'VARTICLE_28524_' => ['values' => ['Standard', 'Urgence', 'Express']],
                'VARTICLE_28541_' => ['values' => ['1' => 'Royal 16 x 24cm', '2' => 'A5 14,8 x 21cm', '3' => 'A4 21 x 29,7cm']],
                'VARTICLE_28514_' => ['type' => 'float', 'values' => false, 'quantity' => true],
            ],
        ];
    }
}
