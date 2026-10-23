<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class PrintProductConfiguratorPrescriptMappingTest extends TestCase
{
    public function testPrescriptMappingComesFromTheResolvedCommercialSourcing(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/src/Yoowii/Pricing/UI/Http/Storefront/Controller/PrintProductConfiguratorController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$sourcing = $sourcingResolver->resolve($productCode, $now);', $controller);
        self::assertStringContainsString('PrintConfiguratorDriverRegistry $driverRegistry', $controller);
        self::assertStringContainsString('$driver = $driverRegistry->get($sourcing->configuratorDriver());', $controller);
        self::assertStringContainsString('$driver->usesSupplierDocument()', $controller);
        self::assertStringContainsString('? $sourcing->mapping()', $controller);
        self::assertStringNotContainsString('prescriptMapping($definitionCode', $controller);
    }
}
