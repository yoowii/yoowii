<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class RealisaprintPrescriptBridgeStimulusTest extends TestCase
{
    public function testPrescriptObservationNeverTriggersAPriceRequestOnItsOwn(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('syncConfiguration()', $controller);
        self::assertStringContainsString('calculate(event)', $controller);
        self::assertStringContainsString('this.requestQuote(code, quantity);', $controller);
        self::assertStringNotContainsString('this.captureCode()', $controller);
        self::assertStringNotContainsString('window.setInterval(() => this.requestQuote', $controller);
    }

    public function testPrescriptPublishesOnlyVisibleCustomerFieldsThroughTheSharedSummaryContract(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString(".web_ligne_variable_saisie", $controller);
        self::assertStringContainsString('classs_usage_reserve', $controller);
        self::assertStringContainsString("yoowii:configuration-update", $controller);
        self::assertStringContainsString('summaryContext: String', $controller);
    }

    public function testPrescriptCanUseAnExplicitVersionedSummaryProfile(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('summaryProfile: Object', $controller);
        self::assertStringContainsString("this.summaryProfileValue?.version === 'v1'", $controller);
        self::assertStringContainsString('profileControl(field)', $controller);
        self::assertStringContainsString('CSS.escape(field.input_id)', $controller);
    }

    public function testAnOutdatedQuoteIsAbortedWhenTheSupplierConfigurationChanges(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('this.quoteAbortController?.abort();', $controller);
        self::assertStringContainsString('signal: abortController.signal', $controller);
        self::assertStringContainsString('sequence !== this.quoteSequence', $controller);
    }

    public function testVisualAdaptationOnlyAddsScopedClassesToSupplierMarkup(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('decorateSupplierDocument()', $controller);
        self::assertStringContainsString("classList.add('yoowii-prescript-document')", $controller);
        self::assertStringContainsString("classList.add('yoowii-prescript-field')", $controller);
        self::assertStringNotContainsString('replaceWith(', $controller);
    }

    private function controller(): string
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/assets/shop/controllers/realisaprint-prescript-quote_controller.js');

        self::assertIsString($controller);

        return $controller;
    }
}
