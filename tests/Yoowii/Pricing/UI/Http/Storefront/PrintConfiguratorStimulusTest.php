<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class PrintConfiguratorStimulusTest extends TestCase
{
    public function testAStaleInitialSnapshotIsAppliedBeforeItsRefreshIsScheduled(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('initialProviderStateStale: Boolean', $controller);
        self::assertStringContainsString("console.debug('[print-configurator] initial provider state'", $controller);
        self::assertStringContainsString('hasInitialProviderStateValue: this.hasInitialProviderStateValue', $controller);
        self::assertStringContainsString('initialProviderStateVisibility: this.initialProviderStateValue?.visibility', $controller);
        self::assertStringContainsString('initialProviderStateStaleValue: this.initialProviderStateStaleValue', $controller);
        self::assertStringContainsString("this.applyProviderState(this.initialProviderStateValue, { source: 'initial' });", $controller);
        self::assertStringContainsString('if (!this.hasInitialProviderStateValue || this.initialProviderStateStaleValue) {', $controller);
        self::assertLessThan(
            strpos($controller, 'this.scheduleRefresh();'),
            strpos($controller, "this.applyProviderState(this.initialProviderStateValue, { source: 'initial' });"),
        );
    }

    public function testARefreshIsOnlyScheduledWhenTheSnapshotIsMissingOrStale(): void
    {
        self::assertStringContainsString('if (!this.hasInitialProviderStateValue || this.initialProviderStateStaleValue) {', $this->controller());
    }

    public function testRefreshFailureDoesNotResetTheAppliedProviderVisibility(): void
    {
        $controller = $this->controller();
        $refreshMethod = substr($controller, strpos($controller, 'async refreshProviderState()'), strpos($controller, 'applyProviderState(state') - strpos($controller, 'async refreshProviderState()'));

        self::assertStringContainsString('const corrected = this.applyProviderState(payload);', $refreshMethod);
        self::assertStringContainsString('this.showError(error.message);', $refreshMethod);
        self::assertStringNotContainsString('this.providerVisibility = {};', $refreshMethod);
        self::assertStringNotContainsString('this.applyProviderState({ visibility: {}', $refreshMethod);
    }

    public function testInitialRefreshNeverCalculatesAPrice(): void
    {
        self::assertStringContainsString('this.manualQuoteValue && this.hasUserInteracted && this.isComplete()', $this->controller());
    }

    public function testRefreshOnlyAutoSelectsWhenOneChoiceRemains(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('if (allowedValues.length !== 1)', $controller);
        self::assertStringContainsString('input.checked = false', $controller);
        self::assertStringNotContainsString('step.dataset.defaultValue || \'\'', $controller);
        self::assertStringNotContainsString('state.current?.[option] || \'\'', $controller);
    }

    public function testAutomaticCorrectionsDoNotTriggerASecondProviderRefresh(): void
    {
        $controller = $this->controller();
        $refreshMethod = substr($controller, strpos($controller, 'async refreshProviderState()'), strpos($controller, 'applyProviderState(state') - strpos($controller, 'async refreshProviderState()'));

        self::assertStringContainsString('this.applyProviderState(payload);', $refreshMethod);
        self::assertStringContainsString('Do not immediately call the supplier', $refreshMethod);
        self::assertStringNotContainsString('if (corrected)', $refreshMethod);
        self::assertStringNotContainsString('this.scheduleRefresh();', $refreshMethod);
    }

    public function testItReusesProviderStatesFromMemoryAndSessionStorage(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('this.providerStateCache = new Map();', $controller);
        self::assertStringContainsString("{ source: 'browser-cache' }", $controller);
        self::assertStringContainsString('window.sessionStorage.getItem(sessionKey)', $controller);
        self::assertStringContainsString('window.sessionStorage.setItem(sessionKey, JSON.stringify(state))', $controller);
        self::assertStringContainsString('mapping_version', $controller);
        self::assertStringContainsString('schema_version', $controller);
    }

    private function controller(): string
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/assets/shop/controllers/print-configurator_controller.js');

        self::assertIsString($controller);

        return $controller;
    }
}
