<?php

declare(strict_types=1);

namespace App\Entity\Order;

use App\Yoowii\Pricing\Domain\Exception\PricingSnapshotCurrencyMismatch;
use App\Yoowii\Pricing\Domain\Exception\PricingSnapshotLocked;
use App\Yoowii\Pricing\Domain\PricingSnapshot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\OrderInterface as CoreOrderInterface;
use Sylius\Component\Core\Model\OrderItem as BaseOrderItem;
use Sylius\Component\Order\Model\OrderItemInterface as BaseOrderItemInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_order_item')]
class OrderItem extends BaseOrderItem
{
    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'pricing_snapshot', type: Types::JSON, nullable: true)]
    private ?array $pricingSnapshot = null;

    public function getPricingSnapshot(): ?PricingSnapshot
    {
        if (null === $this->pricingSnapshot) {
            return null;
        }

        return PricingSnapshot::fromArray($this->pricingSnapshot);
    }

    public function replacePricingSnapshot(PricingSnapshot $pricingSnapshot): void
    {
        $order = $this->getOrder();

        if (null !== $order && ($order->isCheckoutCompleted() || !$order->canBeProcessed())) {
            throw new PricingSnapshotLocked();
        }

        $orderCurrency = $order instanceof CoreOrderInterface ? $order->getCurrencyCode() : null;

        if (null !== $orderCurrency && $orderCurrency !== $pricingSnapshot->currencyCode()) {
            throw new PricingSnapshotCurrencyMismatch(
                $pricingSnapshot->currencyCode(),
                $orderCurrency,
            );
        }

        $this->pricingSnapshot = $pricingSnapshot->toArray();
        $this->setUnitPrice($pricingSnapshot->unitPrice());
        $this->setImmutable(true);
    }

    public function equals(BaseOrderItemInterface $orderItem): bool
    {
        if ($this === $orderItem) {
            return true;
        }

        if (null !== $this->pricingSnapshot) {
            return false;
        }

        if ($orderItem instanceof self && null !== $orderItem->pricingSnapshot) {
            return false;
        }

        return parent::equals($orderItem);
    }
}
