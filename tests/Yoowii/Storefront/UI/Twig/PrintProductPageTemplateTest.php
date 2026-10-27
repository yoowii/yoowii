<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\UI\Twig;

use PHPUnit\Framework\TestCase;

final class PrintProductPageTemplateTest extends TestCase
{
    public function testPrintProductsUseTheDedicatedPageInsteadOfTheDefaultProductHook(): void
    {
        $template = $this->themeTemplate('bundles/SyliusShopBundle/product/show.html.twig');

        self::assertStringContainsString("product.fulfillmentType.value == 'print'", $template);
        self::assertStringContainsString("'shop/product/show/print.html.twig'", $template);
        self::assertStringContainsString("{% hook 'sylius_shop.product.show' with { product, configuration } %}", $template);
    }

    public function testDedicatedPrintPageKeepsTheGalleryAndUsesTheExistingConfiguratorComponent(): void
    {
        $template = $this->themeTemplate('shop/product/show/print.html.twig');

        self::assertStringContainsString('data-product-experience="print"', $template);
        self::assertStringContainsString("PrintProductConfiguratorController::component", $template);
        self::assertStringContainsString('id="yoowii-print-configurator"', $template);
        self::assertStringContainsString('data-configuration-summary-target="dialog"', $template);
        self::assertStringContainsString('Voir le détail', $template);
        self::assertStringContainsString('data-prescript-gallery-slot', $template);
        self::assertStringContainsString('data-controller="yoowii-gallery"', $template);
        self::assertStringContainsString('yoowii:prescript-gallery-update@document->yoowii-gallery#update', $template);
        self::assertStringContainsString('sylius_shop.product.show.content.info.overview.accordion', $template);
        self::assertStringContainsString("sylius_shop.product.show.content.product_listing", $template);
    }

    public function testPrescriptConfiguratorOmitsAnAbsentSummaryProfileValue(): void
    {
        $template = $this->themeTemplate('shop/product/show/realisaprint_prescript_configurator.html.twig');

        self::assertStringContainsString('{% if prescript_summary_profile is not null %}', $template);
        self::assertStringContainsString('data-realisaprint-prescript-quote-summary-profile-value', $template);
    }

    public function testPriceCalculationPrecedesTheSupplierPriceTable(): void
    {
        $template = $this->themeTemplate('shop/product/show/realisaprint_prescript_configurator.html.twig');

        self::assertLessThan(
            strpos($template, 'data-realisaprint-prescript-quote-target="pricing"'),
            strpos($template, 'data-realisaprint-prescript-quote-target="supplierCalculate"'),
        );
    }

    public function testPrescriptLoadingStateKeepsOnlyItsStatusVisible(): void
    {
        $stylesheet = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/assets/css/main.css');

        self::assertIsString($stylesheet);
        self::assertStringContainsString('.yoowii-prescript-shell:not(.is-prescript-ready) .card-body > :not(.yoowii-prescript-status)', $stylesheet);
    }

    private function themeTemplate(string $path): string
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/templates/' . $path);

        self::assertIsString($template);

        return $template;
    }
}
