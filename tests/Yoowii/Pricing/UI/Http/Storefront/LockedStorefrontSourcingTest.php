<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class LockedStorefrontSourcingTest extends TestCase
{
    public function testAQuoteUsesOnlyTheResolvedRouteAndDoesNotFallBackToAnotherSupplier(): void
    {
        $service = file_get_contents(dirname(__DIR__, 6) . '/src/Yoowii/Pricing/Application/PrintQuoteService.php');

        self::assertIsString($service);
        self::assertStringContainsString('public function quoteForRoute(', $service);
        self::assertStringContainsString('[$route]', $service);
        self::assertStringContainsString('Locked storefront sourcing could not be quoted.', $service);
    }

    public function testClassicRefreshUsesTheResolvedMappingInsteadOfSearchingRoutesAgain(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/src/Yoowii/Pricing/UI/Http/Storefront/Controller/PrintProductConfiguratorController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$refresh->refreshForMapping($configuration, $mapping', $controller);
        self::assertStringContainsString('$quoteService->quoteForRoute(', $controller);
    }
}
