<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Infrastructure\Translation;

use App\Yoowii\Sourcing\Infrastructure\Translation\ProductTranslationDictionary;
use PHPUnit\Framework\TestCase;

final class ProductTranslationDictionaryTest extends TestCase
{
    public function testItKeepsFrenchLabelsExactAndReturnsTheirAmericanEnglishTranslations(): void
    {
        $dictionary = new ProductTranslationDictionary(dirname(__DIR__, 5) . '/config/yoowii/product_translations.en_US.json');

        self::assertSame('Business Cards', $dictionary->product('Carte de visite'));
        self::assertSame('Monthly Planner', $dictionary->stock('Agenda mensuel'));
        self::assertNull($dictionary->stock('Stock absent'));
    }
}
