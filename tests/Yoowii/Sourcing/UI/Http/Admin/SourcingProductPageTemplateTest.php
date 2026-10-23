<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin;

use PHPUnit\Framework\TestCase;

final class SourcingProductPageTemplateTest extends TestCase
{
    public function testItExplainsTheStorefrontSourcingDecisionAndFallbacks(): void
    {
        $template = file_get_contents(dirname(__DIR__, 6) . '/templates/admin/sourcing/product_show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('Sourcing storefront résolu', $template);
        self::assertStringContainsString('Décision storefront', $template);
        self::assertStringContainsString('Sourcing de secours', $template);
        self::assertStringContainsString('snapshot', $template);
    }
}
