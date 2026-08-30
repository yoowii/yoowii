<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Entity\Order\OrderItem;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;

final readonly class PrintJobFactory
{
    public function create(OrderItem $item, \DateTimeImmutable $now): PrintJob
    {
        $pricing = $item->getPricingSnapshot();
        if (null === $pricing) {
            throw new \DomainException('A print order item requires an immutable pricing snapshot.');
        }

        $payload = $pricing->toArray();
        $sourcing = $payload['configuration']['sourcing'] ?? null;
        if (!is_array($sourcing)) {
            throw new \DomainException('The pricing snapshot does not contain a supplier selection.');
        }

        $supplierCode = $sourcing['supplier_code'] ?? null;
        $supplierProductCode = $sourcing['supplier_product_code'] ?? null;
        if (!is_string($supplierCode) || !is_string($supplierProductCode)) {
            throw new \DomainException('The supplier selection is malformed.');
        }

        $order = $item->getOrder();
        $orderNumber = $order?->getNumber();
        if (null === $item->getId() || !is_string($orderNumber) || '' === $orderNumber) {
            throw new \DomainException('The paid order item must be persisted and belong to a numbered order.');
        }

        return new PrintJob(
            $item,
            sprintf('PJ-%s-%d', preg_replace('/[^A-Za-z0-9]/', '', $orderNumber), $item->getId()),
            $supplierCode,
            $supplierProductCode,
            [
                'schema_version' => 1,
                'order_number' => $orderNumber,
                'order_item_id' => $item->getId(),
                'variant_code' => $item->getVariant()?->getCode(),
                'quantity' => $item->getQuantity(),
                'pricing' => $payload,
                'supplier' => $sourcing,
                'captured_at' => $now->format(\DateTimeInterface::ATOM),
            ],
            $now,
        );
    }
}
