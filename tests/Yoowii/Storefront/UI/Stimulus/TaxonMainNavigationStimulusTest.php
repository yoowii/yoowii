<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\UI\Stimulus;

use PHPUnit\Framework\TestCase;

final class TaxonMainNavigationStimulusTest extends TestCase
{
    public function testItOnlyShowsOptionalFieldsWhenTheTaxonIsSelected(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 5) . '/assets/admin/controllers/taxon-main-navigation_controller.js');

        self::assertIsString($controller);
        self::assertStringContainsString('this.detailsTarget.hidden = !this.toggleTarget.checked;', $controller);
    }
}
