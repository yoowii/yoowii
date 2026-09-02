<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Entity\Order\Order;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobNotification;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class QueuePrintJobNotification
{
    /** @param list<string> $productionAlertRecipients */
    public function __construct(private EntityManagerInterface $entityManager, private array $productionAlertRecipients = [])
    {
    }

    public function customerStatusChanged(PrintJob $job): void
    {
        $emailCodes = [
            PrintJobStatus::BatReady->value => 'yoowii_print_bat_ready',
            PrintJobStatus::InProduction->value => 'yoowii_print_in_production',
            PrintJobStatus::Shipped->value => 'yoowii_print_shipped',
            PrintJobStatus::Delivered->value => 'yoowii_print_delivered',
        ];
        $emailCode = $emailCodes[$job->status()->value] ?? null;
        $order = $job->orderItem()->getOrder();
        if (!$order instanceof Order) {
            return;
        }
        $recipient = $order->getCustomer()?->getEmail();
        if (!is_string($emailCode) || !is_string($recipient) || '' === trim($recipient)) {
            return;
        }
        $this->queue($job, $emailCode, $recipient);
    }

    public function lateAlert(PrintJob $job, \DateTimeImmutable $day): void
    {
        if (!$job->isLate($day)) {
            return;
        }
        foreach ($this->productionAlertRecipients as $recipient) {
            $recipient = trim($recipient);
            if ('' === $recipient || false === filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $this->queue($job, 'yoowii_print_late_alert_' . $day->format('Y-m-d'), $recipient);
        }
    }

    public function batRejectedByCustomer(PrintJob $job): void
    {
        foreach ($this->productionAlertRecipients as $recipient) {
            $recipient = trim($recipient);
            if ('' !== $recipient && false !== filter_var($recipient, \FILTER_VALIDATE_EMAIL)) {
                $this->queue($job, 'yoowii_print_bat_rejected', $recipient);
            }
        }
    }

    private function queue(PrintJob $job, string $type, string $recipient): void
    {
        $fingerprint = hash('sha256', implode('|', [$job->reference(), $type, strtolower($recipient)]));
        if (null !== $this->entityManager->getRepository(PrintJobNotification::class)->findOneBy(['fingerprint' => $fingerprint])) {
            return;
        }
        $this->entityManager->persist(new PrintJobNotification($job, $fingerprint, $type, $recipient, new \DateTimeImmutable()));
    }
}
