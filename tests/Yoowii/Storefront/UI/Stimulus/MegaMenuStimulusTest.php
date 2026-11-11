<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\UI\Stimulus;

use PHPUnit\Framework\TestCase;

final class MegaMenuStimulusTest extends TestCase
{
    public function testItClosesAsADisclosureAndRestoresFocusAfterEscape(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('closeOnFocusOut()', $controller);
        self::assertStringContainsString('this.lastTrigger?.focus();', $controller);
        self::assertStringNotContainsString('trapFocus', $controller);
    }

    public function testItMeasuresTheHeaderAndSupportsTheLocalMediaCarousel(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("style.setProperty('--header-h'", $controller);
        self::assertStringContainsString('previousSlide(event)', $controller);
        self::assertStringContainsString('nextSlide(event)', $controller);
        self::assertStringContainsString("panel.dataset.hasMediaFeatured === 'true'", $controller);
    }

    public function testItHidesTheFamilyNavigationForFocusedMainNavigationPanels(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("isFocusedMenu(menuId)", $controller);
        self::assertStringContainsString('this.familyNavigationTarget.hidden = focused;', $controller);
    }

    private function controller(): string
    {
        $controller = file_get_contents(dirname(__DIR__, 5) . '/assets/shop/controllers/mega-menu_controller.js');

        self::assertIsString($controller);

        return $controller;
    }
}
