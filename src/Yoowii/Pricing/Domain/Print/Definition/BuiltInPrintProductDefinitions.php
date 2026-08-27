<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Definition;

final class BuiltInPrintProductDefinitions
{
    public static function flyer(): PrintProductDefinition
    {
        return new PrintProductDefinition(
            'PRINT_FLYER',
            '1',
            'matrix_exact',
            [
                'format' => new PrintOptionDefinition('format', PrintOptionType::Code),
                'sides' => new PrintOptionDefinition(
                    'sides',
                    PrintOptionType::Code,
                    allowedValues: ['one_sided', 'two_sided'],
                ),
                'paper' => new PrintOptionDefinition('paper', PrintOptionType::Code),
                'grammage' => new PrintOptionDefinition('grammage', PrintOptionType::Integer, minimum: 1),
                'quantity' => new PrintOptionDefinition('quantity', PrintOptionType::Integer, minimum: 1),
                'finishing' => new PrintOptionDefinition('finishing', PrintOptionType::Code),
            ],
            ['format', 'sides', 'paper', 'grammage', 'quantity', 'finishing'],
        );
    }

    public static function businessCard(): PrintProductDefinition
    {
        return new PrintProductDefinition(
            'PRINT_BUSINESS_CARD',
            '1',
            'matrix_exact',
            [
                'format' => new PrintOptionDefinition('format', PrintOptionType::Code),
                'sides' => new PrintOptionDefinition(
                    'sides',
                    PrintOptionType::Code,
                    allowedValues: ['one_sided', 'two_sided'],
                ),
                'paper' => new PrintOptionDefinition('paper', PrintOptionType::Code),
                'grammage' => new PrintOptionDefinition('grammage', PrintOptionType::Integer, minimum: 1),
                'quantity' => new PrintOptionDefinition('quantity', PrintOptionType::Integer, minimum: 1),
                'finishing' => new PrintOptionDefinition('finishing', PrintOptionType::Code),
                'corners' => new PrintOptionDefinition(
                    'corners',
                    PrintOptionType::Code,
                    allowedValues: ['square', 'rounded'],
                ),
            ],
            ['format', 'sides', 'paper', 'grammage', 'quantity', 'finishing', 'corners'],
        );
    }

    private function __construct()
    {
    }
}
