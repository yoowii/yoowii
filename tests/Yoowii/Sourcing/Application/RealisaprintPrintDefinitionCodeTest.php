<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\RealisaprintPrintDefinitionCode;
use PHPUnit\Framework\TestCase;

final class RealisaprintPrintDefinitionCodeTest extends TestCase
{
    public function testItBuildsOneStableDefinitionCodePerProviderProduct(): void
    {
        self::assertSame('PRINT_REALISAPRINT_356', RealisaprintPrintDefinitionCode::fromProviderProductId('356'));
        self::assertSame('PRINT_REALISAPRINT_BADGE_BOUTON', RealisaprintPrintDefinitionCode::fromProviderProductId('badge bouton'));
    }
}
