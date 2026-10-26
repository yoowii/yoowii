<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use PHPUnit\Framework\TestCase;

final class RealisaprintPrescriptBridgeStimulusTest extends TestCase
{
    public function testPrescriptInstallsASingleSaveConfigurationBridge(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('syncConfiguration()', $controller);
        self::assertStringContainsString('this.saveConfigurationBridge = this.saveConfiguration.bind(this);', $controller);
        self::assertStringContainsString('window.saveConfiguration = this.saveConfigurationBridge;', $controller);
        self::assertStringContainsString('await window.saveConfiguration?.();', $controller);
        self::assertStringNotContainsString('this.captureCode()', $controller);
        self::assertStringNotContainsString('quoteUrlValue', $controller);
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

    public function testPrescriptUsesTheServerAsTheOnlyAuthoritativeQuotePath(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("fetch(this.cartUrlValue", $controller);
        self::assertStringContainsString("window.location.assign(this.cartSummaryUrlValue);", $controller);
        self::assertStringNotContainsString('requestQuote(', $controller);
    }

    public function testPrescriptOffersAQuoteRequestOnlyAfterASupplierRefusal(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('requestQuote = payload.request_quote === true;', $controller);
        self::assertStringContainsString('error.requestQuote === true', $controller);
        self::assertStringContainsString('quoteRequestTarget', $controller);
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
        self::assertStringContainsString('disableSupplierControlTooltips(row)', $controller);
        self::assertStringContainsString("jquery(control).popover('destroy')", $controller);
        self::assertStringContainsString('decorateSupplierPriceTable(table)', $controller);
        self::assertStringContainsString('deliveryKey(value)', $controller);
        self::assertStringContainsString('decorateSupplierPricePopovers(table)', $controller);
        self::assertStringContainsString("popover.classList.add('yoowii-prescript-price-popover')", $controller);
        self::assertStringContainsString('onSupplierPricePopoverHover(event)', $controller);
        self::assertStringContainsString("'vernis-selectif': 'vernis_selectif.png'", $controller);
        self::assertStringContainsString('themes/yoowii-theme/media/prescript-options/', $controller);
        self::assertStringNotContainsString('replaceWith(', $controller);
    }

    public function testPrescriptRequiresANewSupplierPriceAfterConfigurationChanges(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString('markQuoteStale()', $controller);
        self::assertStringContainsString('this.priceNeedsRefresh = true;', $controller);
        self::assertStringContainsString('this.cartRequestKey = null;', $controller);
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
        self::assertStringContainsString('saveConfiguration()', $controller);
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

    public function testItSendsOnlyActiveSupplierVariablesAndAlwaysIncludesTheDelivery(): void
    {
        $controller = $this->controller();

        self::assertStringContainsString("this.configurationScope().querySelectorAll('input, select, textarea')", $controller);
        self::assertStringContainsString('/^VARTICLE_NUMERO_EN_COURS_(\\d+_)$/', $controller);
        self::assertStringContainsString('name !== quantityVariable && shownNames.has(name)', $controller);
        self::assertStringContainsString('name === delayVariable', $controller);
        self::assertStringContainsString('this.isActiveSupplierField(field)', $controller);
        self::assertStringContainsString('this.isActiveSupplierField(control)', $controller);
        self::assertStringContainsString('style.display !== \'none\' && style.visibility !== \'hidden\' && row.getClientRects().length > 0', $controller);
    }

    private function controller(): string
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/assets/shop/controllers/realisaprint-prescript-quote_controller.js');

        self::assertIsString($controller);

        return $controller;
    }
}
