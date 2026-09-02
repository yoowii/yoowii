<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobNotification;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class QueuePrintJobNotification
{
    /** @param list<string> $productionAlertRecipients */
    public function __construct(private EntityManagerInterface $entityManager, private PrintJobAccessLink $links, private array $productionAlertRecipients = [])
    {
    }

    public function customerStatusChanged(PrintJob $job): void
    {
        $messages = [
            PrintJobStatus::BatReady->value => ['Votre BAT est disponible', 'Votre BAT est prêt à être validé.'],
            PrintJobStatus::InProduction->value => ['Votre commande est en production', 'Votre commande est désormais en cours de fabrication.'],
            PrintJobStatus::Shipped->value => ['Votre commande a été expédiée', 'Votre commande a été expédiée.'],
            PrintJobStatus::Delivered->value => ['Votre commande a été livrée', 'Votre commande est indiquée comme livrée.'],
        ];
        $message = $messages[$job->status()->value] ?? null;
        $recipient = $job->orderItem()->getOrder()->getCustomerEmail();
        if (!is_array($message) || !is_string($recipient) || '' === trim($recipient)) {
            return;
        }

        $link = $this->links->show($job, 'fr_FR');
        $this->queue($job, 'customer_' . $job->status()->value, $recipient, sprintf('%s — Yoowii', $message[0]), sprintf("%s\n\nDossier : %s\nSuivre ma commande : %s", $message[1], $job->reference(), $link));
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
            $this->queue($job, 'late_alert_' . $day->format('Y-m-d'), $recipient, sprintf('Action requise — dossier print %s en retard', $job->reference()), sprintf("Le dossier %s est en retard depuis le %s.\nStatut : %s", $job->reference(), $job->dueAt()?->format('d/m/Y H:i'), $job->status()->value));
        }
    }

    private function queue(PrintJob $job, string $type, string $recipient, string $subject, string $content): void
    {
        $fingerprint = hash('sha256', implode('|', [$job->reference(), $type, strtolower($recipient)]));
        if (null !== $this->entityManager->getRepository(PrintJobNotification::class)->findOneBy(['fingerprint' => $fingerprint])) {
            return;
        }
        $this->entityManager->persist(new PrintJobNotification($job, $fingerprint, $type, $recipient, $subject, $content, new \DateTimeImmutable()));
    }
}
