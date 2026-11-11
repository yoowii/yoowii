<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Commerce\Application;

use App\Entity\Product\Product;
use App\Yoowii\Commerce\Application\BulkProductAvailabilityUpdater;
use PHPUnit\Framework\TestCase;

final class BulkProductAvailabilityUpdaterTest extends TestCase
{
    public function testItOnlyChangesProductsWhoseAvailabilityDiffers(): void
    {
        $disabledProduct = new Product();
        $disabledProduct->setEnabled(false);
        $enabledProduct = new Product();
        $enabledProduct->setEnabled(true);

        $updated = (new BulkProductAvailabilityUpdater())->update([$disabledProduct, $enabledProduct], true);

        self::assertSame(1, $updated);
        self::assertTrue($disabledProduct->isEnabled());
        self::assertTrue($enabledProduct->isEnabled());
    }
}
