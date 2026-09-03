<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJobNotification;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

final readonly class SendPendingPrintJobNotifications
{
    public function __construct(private EntityManagerInterface $entityManager, private SenderInterface $sender, private PrintJobAccessLink $links)
    {
    }

    public function __invoke(int $limit = 100): int
    {
        /** @var list<PrintJobNotification> $notifications */
        $notifications = $this->entityManager->createQueryBuilder()
            ->select('notification')->from(PrintJobNotification::class, 'notification')
            ->where('notification.sentAt IS NULL')->orderBy('notification.id', 'ASC')->setMaxResults($limit)
            ->getQuery()->getResult();
        $sent = 0;
        foreach ($notifications as $notification) {
            $this->sender->send($this->emailCode($notification), [$notification->recipient()], [
                'printJob' => $notification->printJob(),
                'printJobLink' => $this->links->show($notification->printJob(), 'fr_FR'),
            ]);
            $notification->markSent(new \DateTimeImmutable());
            ++$sent;
        }
        $this->entityManager->flush();

        return $sent;
    }

    private function emailCode(PrintJobNotification $notification): string
    {
        if (str_starts_with($notification->type(), 'yoowii_print_late_alert_')) {
            return 'yoowii_print_late_alert';
        }
        if (str_starts_with($notification->type(), 'yoowii_print_artwork_correction_requested_')) {
            return 'yoowii_print_artwork_correction_requested';
        }

        return $notification->type();
    }
}
