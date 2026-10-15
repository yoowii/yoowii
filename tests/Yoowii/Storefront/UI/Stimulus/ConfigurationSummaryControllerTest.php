<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\UI\Stimulus;

use PHPUnit\Framework\TestCase;

final class ConfigurationSummaryControllerTest extends TestCase
{
    public function testItRendersOnlyTheNormalizedConfigurationState(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("document.addEventListener('yoowii:configuration-update'", $controller);
        self::assertStringContainsString('context !== this.contextValue || !Array.isArray(steps)', $controller);
        self::assertStringContainsString('this.listTargets.forEach', $controller);
        self::assertStringContainsString('target.replaceChildren', $controller);
        self::assertStringNotContainsString('innerHTML', $controller);
    }

    public function testItPublishesAccessibleEditAndResetRequestsWithoutKnowingTheConfigurator(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("yoowii:configuration-edit", $controller);
        self::assertStringContainsString("yoowii:configuration-reset", $controller);
        self::assertStringContainsString('edit.href = `#yoowii-print-step-${step.code}`', $controller);
    }

    public function testItControlsTheMobileSummaryDialogWithoutKnowingTheConfigurator(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('this.dialogTarget.showModal()', $controller);
        self::assertStringContainsString('this.dialogTarget.close()', $controller);
    }

    private function controller(): string
    {
        $controller = file_get_contents(dirname(__DIR__, 5) . '/assets/shop/controllers/configuration-summary_controller.js');

        self::assertIsString($controller);

        return $controller;
    }
}
