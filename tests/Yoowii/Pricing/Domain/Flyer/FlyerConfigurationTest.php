<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Flyer;

use App\Yoowii\Pricing\Domain\Flyer\FlyerConfiguration;
use PHPUnit\Framework\TestCase;

final class FlyerConfigurationTest extends TestCase
{
    public function testItBuildsAStableMatrixKey(): void
    {
        $configuration = new FlyerConfiguration('a5', 'two_sided', 'coated_gloss', 135, 1000, 'none');

        self::assertSame('a5|two_sided|coated_gloss|135|1000|none', $configuration->matrixKey());
        self::assertSame(1000, $configuration->toArray()['quantity']);
    }

    public function testItRejectsAnUnknownSidesValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Flyer sides must be');

        new FlyerConfiguration('a5', 'recto-verso', 'coated_gloss', 135, 1000, 'none');
    }
}
