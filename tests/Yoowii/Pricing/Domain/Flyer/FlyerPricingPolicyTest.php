<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Flyer;

use App\Yoowii\Pricing\Domain\Flyer\FlyerPricingPolicy;
use PHPUnit\Framework\TestCase;

final class FlyerPricingPolicyTest extends TestCase
{
    public function testItUsesThePercentageMarkupWhenItExceedsTheMinimum(): void
    {
        $policy = new FlyerPricingPolicy('2026-09', 3500, 1200, 500);

        self::assertSame(1400, $policy->calculateMargin(4000));
        self::assertSame(500, $policy->handlingFee());
    }

    public function testItUsesTheMinimumMarginForSmallOrders(): void
    {
        $policy = new FlyerPricingPolicy('2026-09', 3500, 1200);

        self::assertSame(1200, $policy->calculateMargin(1000));
    }

    public function testPercentageMarkupIsRoundedUpToTheNextCent(): void
    {
        $policy = new FlyerPricingPolicy('2026-09', 3333, 0);

        self::assertSame(334, $policy->calculateMargin(1000));
    }
}
