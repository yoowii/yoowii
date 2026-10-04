<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintMappingCompleteness;
use PHPUnit\Framework\TestCase;

final class RealisaprintFixedTextTest extends TestCase
{
    public function testReadonlyTextIsMappedToTheExactProviderDefault(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_BADGE', 'v1', [
            'face_imprimee' => [
                'type' => 'code', 'required' => true, 'allowed_values' => ['recto'],
                'default' => 'recto', 'provider_variable' => 'VARTICLE_28779_',
            ],
        ], ['face_imprimee']);
        (new RealisaprintMappingCompleteness())->assertComplete($definition, [
            'stocks' => ['7' => 'Badge bouton'],
            'variables' => ['VARTICLE_28779_' => ['type' => 'text', 'readonly' => true, 'values' => false, 'default' => 'Recto']],
        ], [
            'stock' => '7',
            'variables' => ['VARTICLE_28779_' => ['option' => 'face_imprimee', 'values' => ['recto' => 'Recto']]],
        ]);
        self::assertSame('recto', $definition->definition()->configure(['face_imprimee' => 'recto'])->toArray()['face_imprimee']);
    }

    public function testReadonlyTextRejectsAStaleDefault(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_BADGE', 'v1', [
            'face_imprimee' => ['type' => 'code', 'allowed_values' => ['recto'], 'provider_variable' => 'VARTICLE_28779_'],
        ], ['face_imprimee']);
        $this->expectException(\InvalidArgumentException::class);
        (new RealisaprintMappingCompleteness())->assertComplete($definition, [
            'stocks' => ['7' => 'Badge bouton'],
            'variables' => ['VARTICLE_28779_' => ['readonly' => true, 'values' => false, 'default' => 'Verso']],
        ], [
            'stock' => '7',
            'variables' => ['VARTICLE_28779_' => ['option' => 'face_imprimee', 'values' => ['recto' => 'Recto']]],
        ]);
    }

    public function testFreeTextAcceptsACommercialLabel(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_BADGE', 'v1', [
            'couleur_d_impression' => ['type' => 'text', 'allowed_values' => [], 'provider_variable' => 'VARTICLE_28778_'],
        ], ['couleur_d_impression']);
        self::assertSame(
            'Quadri recto',
            $definition->definition()->configure(['couleur_d_impression' => 'Quadri recto'])->toArray()['couleur_d_impression'],
        );
    }

    public function testPublishedDefinitionCannotBeRepairedInPlace(): void
    {
        $definition = new PersistedPrintProductDefinition('PRINT_BADGE', 'v1', [
            'face_imprimee' => ['type' => 'code', 'allowed_values' => []],
        ], ['face_imprimee']);
        $definition->activate();
        $this->expectException(\DomainException::class);
        $definition->replaceDraftOptions(['face_imprimee' => ['type' => 'code', 'allowed_values' => ['recto']]]);
    }
}
