<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use PHPUnit\Framework\TestCase;

final class BuiltInPrintProductDefinitionRegistryTest extends TestCase
{
    public function testItExposesTheMvpPrintProducts(): void
    {
        $registry = new BuiltInPrintProductDefinitionRegistry();

        self::assertSame(['PRINT_FLYER', 'PRINT_BUSINESS_CARD'], $registry->codes());
        self::assertSame('PRINT_FLYER', $registry->get('PRINT_FLYER')->productCode());
    }

    public function testItRejectsAnUnknownProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BuiltInPrintProductDefinitionRegistry())->get('PRINT_UNKNOWN');
    }
}
