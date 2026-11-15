<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\UI\Twig;

use PHPUnit\Framework\TestCase;

final class PrintProductionTimelineTemplateTest extends TestCase
{
    public function testItRendersAStableCheckIconForCompletedProductionSteps(): void
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/templates/shop/flow/print_job/show.html.twig');
        $stylesheet = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/assets/css/main.css');

        self::assertIsString($template);
        self::assertIsString($stylesheet);
        self::assertStringContainsString("{% if is_past %}<svg viewBox=\"0 0 16 16\"", $template);
        self::assertStringContainsString('display: inline-flex;', $stylesheet);
        self::assertStringContainsString('align-items: center;', $stylesheet);
        self::assertStringContainsString('justify-content: center;', $stylesheet);
        self::assertStringContainsString('li.is-complete > span:first-child svg', $stylesheet);
    }
}
