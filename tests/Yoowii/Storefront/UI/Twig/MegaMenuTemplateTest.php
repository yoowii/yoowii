<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\UI\Twig;

use PHPUnit\Framework\TestCase;

final class MegaMenuTemplateTest extends TestCase
{
    public function testItUsesTheCurrentChannelMenuTaxonRootForTheCatalogueLink(): void
    {
        $template = $this->menuTemplate();

        self::assertStringContainsString('{% set channel_menu_taxon = sylius.channel is defined ? sylius.channel.menuTaxon : null %}', $template);
        self::assertStringContainsString('{% set catalogue_taxon = channel_menu_taxon ? channel_menu_taxon.root : null %}', $template);
        self::assertStringNotContainsString('{% set catalogue_taxon = menu_taxons|first %}', $template);
    }

    public function testItUsesThePluginFeaturedMediaZoneAndOneServicesFallback(): void
    {
        $template = $this->menuTemplate();

        self::assertStringContainsString('{% set mediaFeatured = taxon.images|filter(', $template);
        self::assertStringContainsString("image.type|default('') == 'featured'", $template);
        self::assertSame(1, substr_count($template, 'data-mega-menu-target="servicePanel"'));
        self::assertStringContainsString('grid-cols-[270px_minmax(0,1fr)_25%]', $template);
    }

    public function testItUsesDisclosureSemanticsAndAccessibleProgressiveNavigation(): void
    {
        $template = $this->menuTemplate();

        self::assertStringNotContainsString('role="dialog"', $template);
        self::assertStringNotContainsString('trapFocus', $template);
        self::assertStringContainsString('data-action="mouseenter->mega-menu#openFromTrigger focus->mega-menu#openFromTrigger"', $template);
        self::assertStringContainsString("path('yoowii_shop_print_catalog'", $template);
        self::assertStringContainsString('focus-visible:outline focus-visible:outline-2', $template);
    }

    public function testItUsesExplicitYoowiiNavigationTaxonsAndKeepsLeavesAsLinks(): void
    {
        $template = $this->menuTemplate();

        self::assertStringContainsString('{% set main_navigation_taxons = yoowii_main_navigation_taxons() %}', $template);
        self::assertStringContainsString('mega_menu_enabled and taxon.hasPanel', $template);
        self::assertStringContainsString('data-mega-menu-menu-id-param="main-{{ taxon.id }}"', $template);
        self::assertStringContainsString('data-mobile-menu-screen-id="main-{{ taxon.id }}"', $template);
        self::assertStringContainsString("'yoowii.storefront.mega_menu.quick_access'|trans", $template);
        self::assertStringContainsString("product.imagesByType('thumbnail').first", $template);
        self::assertStringContainsString('taxon.hasMoreProducts', $template);
    }

    public function testItRendersAFocusedPanelWithoutChangingTheAllProductsPanel(): void
    {
        $template = $this->menuTemplate();

        self::assertStringContainsString('data-mega-menu-target="familyNavigation"', $template);
        self::assertStringContainsString('col-span-3 min-w-0', $template);
        self::assertStringContainsString('taxon.parent ? path', $template);
        self::assertStringContainsString("'yoowii.storefront.mega_menu.view_all_products'", $template);
    }

    private function menuTemplate(): string
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/templates/bundles/SyliusShopBundle/shared/layout/base/header/navbar/menu.html.twig');

        self::assertIsString($template);

        return $template;
    }
}
