<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\UI\Http\Admin\Controller\RealisaprintPublicationController;
use PHPUnit\Framework\TestCase;

final class RealisaprintPublicationControllerTest extends TestCase
{
    public function testTestFieldsUseControlsSuitedToThePublishedOptionType(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_TEST', 'v1', [
            'format' => [
                'type' => 'code',
                'provider_type' => 'select',
                'label' => 'Format',
                'allowed_values' => ['a4', 'a5'],
                'value_labels' => ['a4' => 'A4', 'a5' => 'A5'],
                'default' => 'a4',
            ],
            'reference' => ['type' => 'text', 'label' => 'Référence', 'allowed_values' => [], 'default' => 'Essai'],
            'quantity' => ['type' => 'integer', 'label' => 'Quantité', 'allowed_values' => [], 'minimum' => 1, 'maximum' => 1000, 'default' => 50],
        ], ['format']);
        $mapping = $this->createMock(SupplierProductMappingVersion::class);
        $mapping->method('configurationMapping')->willReturn(['realisaprint' => ['variables' => []]]);

        /** @var array<string, array{control: string, input_type: string, choices: array<string, string>, editable: bool}> $fields */
        $fields = (new \ReflectionMethod(RealisaprintPublicationController::class, 'testFields'))
            ->invoke(new RealisaprintPublicationController(), $definition, $mapping);

        self::assertSame('select', $fields['format']['control']);
        self::assertSame(['a4' => 'A4', 'a5' => 'A5'], $fields['format']['choices']);
        self::assertSame('input', $fields['reference']['control']);
        self::assertSame('text', $fields['reference']['input_type']);
        self::assertSame('input', $fields['quantity']['control']);
        self::assertSame('number', $fields['quantity']['input_type']);
        self::assertArrayNotHasKey('required', $fields['quantity']);
        self::assertArrayNotHasKey('minimum', $fields['quantity']);
        self::assertArrayNotHasKey('maximum', $fields['quantity']);
    }
}
