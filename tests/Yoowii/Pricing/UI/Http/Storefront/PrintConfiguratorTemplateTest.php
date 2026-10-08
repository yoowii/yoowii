<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class PrintConfiguratorTemplateTest extends TestCase
{
    public function testProviderHiddenOptionsAreHiddenAndDisabledAtFirstRender(): void
    {
        $template = $this->template();

        self::assertStringContainsString("not initially_visible ? ' d-none'", $template);
        self::assertStringContainsString('disabled: not initially_visible', $template);
        self::assertStringContainsString("aria-hidden=\"{{ initially_visible ? 'false' : 'true' }}\"", $template);
        self::assertStringContainsString("initial_visibility[option_code] is defined and not initial_visibility[option_code] ? ' d-none'", $template);
    }

    public function testStepsUseOnlyTheCanonicalFormFieldCode(): void
    {
        $template = $this->template();

        self::assertStringContainsString('data-option-code="{{ field.vars.name }}"', $template);
        self::assertStringNotContainsString('data-axis=', $template);
    }

    private function template(): string
    {
        $template = file_get_contents(dirname(__DIR__, 6) . '/templates/shop/product/show/print_configurator.html.twig');

        self::assertIsString($template);

        return $template;
    }
}
