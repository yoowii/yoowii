<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Entity\Order\Order;
use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CreatePrintJobsForPaidOrder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrintJobFactory $factory,
    ) {
    }

    /** @return list<PrintJob> */
    public function __invoke(Order $order, ?\DateTimeImmutable $now = null): array
    {
        if ('paid' !== $order->getPaymentState()) {
            return [];
        }

        $created = [];
        $at = $now ?? new \DateTimeImmutable();
        foreach ($order->getItems() as $item) {
            if (!$item instanceof OrderItem) {
                continue;
            }
            $product = $item->getVariant()?->getProduct();
            if (!$product instanceof Product || FulfillmentType::Print !== $product->getFulfillmentType()) {
                continue;
            }

            $existing = $this->entityManager->getRepository(PrintJob::class)->findOneBy(['orderItem' => $item]);
            if ($existing instanceof PrintJob) {
                continue;
            }

            $job = $this->factory->create($item, $at);
            $this->entityManager->persist($job);
            $created[] = $job;
        }

        if ([] !== $created) {
            $this->entityManager->flush();
        }

        return $created;
    }
}
