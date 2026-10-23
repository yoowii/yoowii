<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class StorefrontPrintSourcingSeparationTest extends TestCase
{
    public function testProductPageResolvesOneSourcingBeforeChoosingTheConfigurator(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/src/Yoowii/Pricing/UI/Http/Storefront/Controller/PrintProductConfiguratorController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('StorefrontPrintSourcingResolver $sourcingResolver', $controller);
        self::assertStringContainsString('$sourcing = $sourcingResolver->resolve($productCode, $now);', $controller);
        self::assertStringContainsString('$sourcing->route()', $controller);
        self::assertStringContainsString('ConfiguratorKind::SupplierApi === $sourcing->configuratorKind()', $controller);
    }

    public function testResolverTreatsAnUnpublishedApiMappingAsIneligibleAndContinuesToTheNextRoute(): void
    {
        $resolver = file_get_contents(dirname(__DIR__, 6) . '/src/Yoowii/Pricing/Application/StorefrontPrintSourcingResolver.php');

        self::assertIsString($resolver);
        self::assertStringContainsString('foreach ($routes as $route)', $resolver);
        self::assertStringContainsString('null !== $mapping->publishedAt() && null === $mapping->archivedAt()', $resolver);
        self::assertStringContainsString('continue;', $resolver);
    }
}
