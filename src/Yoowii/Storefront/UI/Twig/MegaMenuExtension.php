<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\UI\Twig;

use App\Yoowii\Storefront\Application\MainNavigationTaxonResolver;
use App\Yoowii\Storefront\Application\MegaMenuProductPreloader;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MegaMenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MegaMenuProductPreloader $productPreloader,
        private readonly MainNavigationTaxonResolver $mainNavigationTaxonResolver,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('yoowii_preload_mega_menu_products', $this->productPreloader->preload(...)),
            new TwigFunction('yoowii_mega_menu_products', $this->productPreloader->productsFor(...)),
            new TwigFunction('yoowii_main_navigation_taxons', $this->mainNavigationTaxonResolver->resolve(...)),
        ];
    }
}
