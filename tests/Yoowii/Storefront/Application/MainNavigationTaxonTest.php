<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\Application;

use App\Yoowii\Storefront\Application\MainNavigationTaxon;
use PHPUnit\Framework\TestCase;

final class MainNavigationTaxonTest extends TestCase
{
    public function testItExposesTheFocusedProductPanelState(): void
    {
        $taxon = new MainNavigationTaxon(47, 'Cartes de visite', 'cartes-de-visite', 'fr_FR', 2, false, true, true, true, null, [], []);

        self::assertFalse($taxon->hasChildren);
        self::assertTrue($taxon->hasProducts);
        self::assertTrue($taxon->hasMoreProducts);
        self::assertTrue($taxon->hasPanel);
    }
}
