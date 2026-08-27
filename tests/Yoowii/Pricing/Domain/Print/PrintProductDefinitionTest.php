<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Print;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use PHPUnit\Framework\TestCase;

final class PrintProductDefinitionTest extends TestCase
{
    public function testFlyerDefinitionKeepsTheLegacyMatrixKey(): void
    {
        $configuration = BuiltInPrintProductDefinitions::flyer()->configure([
            'format' => 'A5',
            'sides' => 'two_sided',
            'paper' => 'coated_gloss',
            'grammage' => '135',
            'quantity' => 1000,
            'finishing' => 'none',
        ]);

        self::assertSame('PRINT_FLYER', $configuration->productCode());
        self::assertSame('a5|two_sided|coated_gloss|135|1000|none', $configuration->matrixKey());
        self::assertSame(135, $configuration->toArray()['grammage']);
    }

    public function testBusinessCardDefinitionAddsItsOwnPricingAxis(): void
    {
        $configuration = BuiltInPrintProductDefinitions::businessCard()->configure([
            'format' => '85x55',
            'sides' => 'two_sided',
            'paper' => 'coated_matt',
            'grammage' => 350,
            'quantity' => 500,
            'finishing' => 'matte_lamination',
            'corners' => 'rounded',
        ]);

        self::assertSame('PRINT_BUSINESS_CARD', $configuration->productCode());
        self::assertSame(
            '85x55|two_sided|coated_matt|350|500|matte_lamination|rounded',
            $configuration->matrixKey(),
        );
    }

    public function testDefinitionRejectsMissingAndUnknownOptions(): void
    {
        $definition = BuiltInPrintProductDefinitions::businessCard();

        try {
            $definition->configure([
                'format' => '85x55',
                'sides' => 'two_sided',
                'paper' => 'coated_matt',
                'grammage' => 350,
                'quantity' => 500,
                'finishing' => 'none',
            ]);
            self::fail('Missing corners should have been rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Required print option "corners" is missing.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown print option(s): color_profile.');

        $definition->configure([
            'format' => '85x55',
            'sides' => 'two_sided',
            'paper' => 'coated_matt',
            'grammage' => 350,
            'quantity' => 500,
            'finishing' => 'none',
            'corners' => 'square',
            'color_profile' => 'cmyk',
        ]);
    }
}
