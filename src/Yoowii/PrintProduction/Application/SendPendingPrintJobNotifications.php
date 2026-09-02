<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJobNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SendPendingPrintJobNotifications
{
    public function __construct(private EntityManagerInterface $entityManager, private MailerInterface $mailer, private string $sender)
    {
    }

    public function __invoke(int $limit = 100): int
    {
        $notifications = $this->entityManager->createQueryBuilder()
            ->select('notification')->from(PrintJobNotification::class, 'notification')
            ->where('notification.sentAt IS NULL')->orderBy('notification.id', 'ASC')->setMaxResults($limit)
            ->getQuery()->getResult();
        $sent = 0;
        foreach ($notifications as $notification) {
            if (!$notification instanceof PrintJobNotification) { continue; }
            $this->mailer->send((new Email())->from($this->sender)->to($notification->recipient())->subject($notification->subject())->text($notification->content()));
            $notification->markSent(new \DateTimeImmutable());
            ++$sent;
        }
        $this->entityManager->flush();

        return $sent;
    }
}
