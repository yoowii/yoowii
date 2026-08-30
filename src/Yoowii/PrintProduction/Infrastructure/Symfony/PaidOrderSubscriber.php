<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Symfony;

use App\Entity\Order\Order;
use App\Entity\Payment\Payment;
use App\Yoowii\PrintProduction\Application\CreatePrintJobsForPaidOrder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

final readonly class PaidOrderSubscriber implements EventSubscriberInterface
{
    public function __construct(private CreatePrintJobsForPaidOrder $createPrintJobs)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sylius.payment.post_complete' => 'onPaymentStateChanged',
            'sylius.order.post_payment' => 'onPaymentStateChanged',
        ];
    }

    public function onPaymentStateChanged(GenericEvent $event): void
    {
        $subject = $event->getSubject();
        $order = $subject instanceof Payment ? $subject->getOrder() : $subject;
        if ($order instanceof Order && 'paid' === $order->getPaymentState()) {
            ($this->createPrintJobs)($order);
        }
    }
}
