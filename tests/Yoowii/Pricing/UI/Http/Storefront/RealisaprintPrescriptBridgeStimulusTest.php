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
        self::assertStringContainsString('visibleText(element)', $controller);
        self::assertStringContainsString("document.createTreeWalker(element, NodeFilter.SHOW_TEXT", $controller);
    }

    public function testPrescriptCanUseAnExplicitVersionedSummaryProfile(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("getAttribute('data-realisaprint-prescript-quote-summary-profile-value')", $controller);
        self::assertStringContainsString("if (!serializedProfile || serializedProfile === 'null') return null;", $controller);
        self::assertStringContainsString('profile = JSON.parse(serializedProfile);', $controller);
        self::assertStringContainsString("profile?.version === 'v1'", $controller);
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
        self::assertStringContainsString('decorateSupplierTooltip(row)', $controller);
        self::assertStringContainsString("if (!hasImage && this.visibleText(content) === '')", $controller);
        self::assertStringContainsString('tooltip.hidden = true;', $controller);
        self::assertStringContainsString("'yoowii-prescript-tooltip-details'", $controller);
        self::assertStringContainsString('title.after(tooltip)', $controller);
        self::assertStringContainsString("row.classList.add('yoowii-prescript-field--label-tooltip')", $controller);
        self::assertStringContainsString('disableUnavailableSupplierControls(row)', $controller);
        self::assertStringContainsString("row.classList.contains('css_zone_grisee')", $controller);
        self::assertStringContainsString('control.disabled = true;', $controller);
        self::assertStringContainsString("'vernis-selectif': 'vernis_selectif.png'", $controller);
        self::assertStringContainsString('themes/yoowii-theme/media/prescript-options/', $controller);
        self::assertStringNotContainsString('replaceWith(', $controller);
    }

    public function testPrescriptPriceIsMarkedStaleWhenTheSupplierConfigurationChanges(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('markQuoteStale()', $controller);
        self::assertStringContainsString('setQuoteStale(false)', $controller);
        self::assertStringContainsString('quoteStale', $controller);
    }

    public function testItPublishesSupplierMediaToTheYoowiiGalleryAndPlacesOptionsInTheProductLayout(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("querySelector('.contenu_bloc_image_tableau')", $controller);
        self::assertStringContainsString("yoowii:prescript-gallery-update", $controller);
        self::assertStringContainsString('scheduleGalleryUpdate()', $controller);
        self::assertStringContainsString('safeImageUrl(value)', $controller);
        self::assertStringContainsString("attributeFilter: ['src']", $controller);
        self::assertStringContainsString("querySelectorAll('[id^=\"BALISE_BLOC_\"]')", $controller);
        self::assertStringContainsString("querySelectorAll('[id^=\"VAR_\"]')", $controller);
        self::assertStringContainsString("this.configurationScope().querySelectorAll('[id^=\"VAR_\"]')", $controller);
        self::assertStringContainsString('this.optionsTarget.append(block)', $controller);
        self::assertStringNotContainsString('cloneNode(', $controller);
        self::assertStringNotContainsString('resizeSupplierGallery(', $controller);
        self::assertStringNotContainsString('gallerySlot.append(gallery)', $controller);
        self::assertStringNotContainsString('new ResizeObserver', $controller);
        self::assertStringContainsString('calculateSupplierPrice(event)', $controller);
        self::assertStringContainsString('clearError()', $controller);
        self::assertStringContainsString('waitForConfigurationCode()', $controller);
        self::assertStringContainsString('submitQuotedItem()', $controller);
    }

    public function testVisibleGalleryIsOwnedByTheYoowiiStimulusController(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/assets/shop/controllers/yoowii-gallery_controller.js');

        self::assertIsString($controller);
        self::assertStringContainsString('this.thumbnailsTarget.replaceChildren', $controller);
        self::assertStringContainsString("thumbnail.loading = 'lazy'", $controller);
    }

    public function testAddToCartIsOnlyRevealedAfterChoosingASupplierPrice(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('const selected = this.selectedPriceOption();', $controller);
        self::assertStringContainsString("this.addToCartTarget.classList.toggle('hidden', !selected);", $controller);
    }

    private function controller(): string
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/assets/shop/controllers/realisaprint-prescript-quote_controller.js');

        self::assertIsString($controller);

        return $controller;
    }
}
