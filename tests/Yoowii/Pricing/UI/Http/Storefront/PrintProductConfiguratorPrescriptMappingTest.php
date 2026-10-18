<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class PrintProductConfiguratorPrescriptMappingTest extends TestCase
{
    public function testPrescriptMappingIsResolvedFromTheCommercialProductRatherThanTheSharedDefinition(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/src/Yoowii/Pricing/UI/Http/Storefront/Controller/PrintProductConfiguratorController.php');

        self::assertIsString($controller);
        self::assertStringContainsString('$prescriptMapping = $this->prescriptMapping($productCode, $entityManager);', $controller);
        self::assertStringContainsString('private function prescriptMapping(string $productCode,', $controller);
        self::assertStringContainsString("findBy(['yoowiiProductCode' => \$productCode, 'active' => true])", $controller);
        self::assertStringNotContainsString('$prescriptMapping = $this->prescriptMapping($definitionCode, $entityManager);', $controller);
    }
}
