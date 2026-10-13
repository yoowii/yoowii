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
        self::assertStringContainsString('data-action="mouseenter->mega-menu#openFamily focus->mega-menu#openFamily"', $template);
        self::assertStringContainsString("path('yoowii_shop_print_catalog'", $template);
        self::assertStringContainsString('focus-visible:outline focus-visible:outline-2', $template);
    }

    private function menuTemplate(): string
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/themes/YoowiiTheme/templates/bundles/SyliusShopBundle/shared/layout/base/header/navbar/menu.html.twig');

        self::assertIsString($template);

        return $template;
    }
}
