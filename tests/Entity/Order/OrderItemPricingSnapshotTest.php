<?php

declare(strict_types=1);

namespace App\Tests\Entity\Order;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Yoowii\Pricing\Domain\Exception\PricingSnapshotCurrencyMismatch;
use App\Yoowii\Pricing\Domain\Exception\PricingSnapshotLocked;
use App\Yoowii\Pricing\Domain\PricingSnapshot;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Order\Model\OrderInterface as BaseOrderInterface;

final class OrderItemPricingSnapshotTest extends TestCase
{
    public function testItHasNoSnapshotByDefault(): void
    {
        self::assertNull((new OrderItem())->getPricingSnapshot());
    }

    public function testItStoresTheSnapshotAndProtectsTheCustomUnitPrice(): void
    {
        $orderItem = new OrderItem();
        $snapshot = self::createSnapshot(12900);

        $orderItem->replacePricingSnapshot($snapshot);

        self::assertSame($snapshot->toArray(), $orderItem->getPricingSnapshot()?->toArray());
        self::assertSame(12900, $orderItem->getUnitPrice());
        self::assertTrue($orderItem->isImmutable());
    }

    public function testItCanBeRecalculatedWhileTheOrderIsACart(): void
    {
        $order = new Order();
        $orderItem = new OrderItem();
        $order->addItem($orderItem);

        $orderItem->replacePricingSnapshot(self::createSnapshot(12900));
        $orderItem->replacePricingSnapshot(self::createSnapshot(14900));

        self::assertSame(14900, $orderItem->getPricingSnapshot()?->unitPrice());
        self::assertSame(14900, $orderItem->getUnitPrice());
    }

    public function testItCannotBeChangedAfterCheckoutCompletion(): void
    {
        $order = new Order();
        $orderItem = new OrderItem();
        $order->addItem($orderItem);
        $orderItem->replacePricingSnapshot(self::createSnapshot(12900));
        $order->completeCheckout();

        $this->expectException(PricingSnapshotLocked::class);

        $orderItem->replacePricingSnapshot(self::createSnapshot(14900));
    }

    public function testItCannotBeChangedWhenTheOrderIsNoLongerACart(): void
    {
        $order = new Order();
        $orderItem = new OrderItem();
        $order->addItem($orderItem);
        $orderItem->replacePricingSnapshot(self::createSnapshot(12900));
        $order->setState(BaseOrderInterface::STATE_NEW);

        $this->expectException(PricingSnapshotLocked::class);

        $orderItem->replacePricingSnapshot(self::createSnapshot(14900));
    }

    public function testItsCurrencyMustMatchTheOrderCurrency(): void
    {
        $order = new Order();
        $order->setCurrencyCode('USD');
        $orderItem = new OrderItem();
        $order->addItem($orderItem);

        $this->expectException(PricingSnapshotCurrencyMismatch::class);

        $orderItem->replacePricingSnapshot(self::createSnapshot(12900));
    }

    public function testConfiguredItemsAreNeverMerged(): void
    {
        $firstItem = new OrderItem();
        $firstItem->replacePricingSnapshot(self::createSnapshot(12900));
        $secondItem = new OrderItem();
        $secondItem->replacePricingSnapshot(self::createSnapshot(12900));

        self::assertFalse($firstItem->equals($secondItem));
        self::assertTrue($firstItem->equals($firstItem));
    }

    private static function createSnapshot(int $unitPrice): PricingSnapshot
    {
        return new PricingSnapshot(
            'print.flyer',
            '2026-08-01',
            ['format' => 'A5', 'quantity' => 1000],
            ['production' => $unitPrice, 'total' => $unitPrice],
            $unitPrice,
            'EUR',
            new \DateTimeImmutable('2026-08-27T14:00:00+00:00'),
        );
    }
}
