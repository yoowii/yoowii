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

    public function testItPublishesTheStaleFlagAlongsideTheInitialProviderState(): void
    {
        $template = $this->template();

        self::assertStringContainsString('data-print-configurator-initial-provider-state-value=', $template);
        self::assertStringContainsString('data-print-configurator-initial-provider-state-stale-value="{{ initial_provider_state_stale|default(false) ? \'true\' : \'false\' }}"', $template);
    }

    public function testDorureAChaudVisibilityIsAppliedByTwigBeforeStimulusConnects(): void
    {
        $template = $this->template();

        self::assertStringContainsString('{% set initially_visible = initial_visibility[option_code] is defined ? initial_visibility[option_code] : true %}', $template);
        self::assertStringContainsString("{{ not initially_visible ? ' d-none' : '' }}", $template);
        self::assertStringContainsString('disabled: not initially_visible', $template);
        self::assertStringContainsString('aria-hidden="{{ initially_visible ? \'false\' : \'true\' }}"', $template);
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
