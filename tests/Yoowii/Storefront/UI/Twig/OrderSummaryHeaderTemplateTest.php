<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\UI\Twig;

use PHPUnit\Framework\TestCase;

final class OrderSummaryHeaderTemplateTest extends TestCase
{
    public function testItPresentsOrderMetadataAsLabelledResponsiveDetails(): void
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/templates/bundles/SyliusShopBundle/order/show/content/header.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('sm:grid-cols-3', $template);
        self::assertStringContainsString("'yoowii.storefront.order_summary.placed_on'|trans", $template);
        self::assertStringContainsString("'yoowii.storefront.order_summary.total'|trans", $template);
        self::assertStringContainsString("'yoowii.storefront.order_summary.products'|trans", $template);
        self::assertStringContainsString("order.checkoutCompletedAt|format_datetime('long', 'short')", $template);
        self::assertStringContainsString("datetime=\"{{ order.checkoutCompletedAt|date('c') }}\"", $template);
    }
}
