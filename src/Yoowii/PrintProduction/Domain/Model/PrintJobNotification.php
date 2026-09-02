<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job_notification')]
#[ORM\UniqueConstraint(name: 'uniq_print_notification_fingerprint', columns: ['fingerprint'])]
class PrintJobNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'print_job_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintJob $printJob,
        #[ORM\Column(type: Types::STRING, length: 128)]
        private readonly string $fingerprint,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $type,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private readonly string $recipient,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private readonly string $subject,
        #[ORM\Column(type: Types::TEXT)]
        private readonly string $content,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int { return $this->id; }
    public function printJob(): PrintJob { return $this->printJob; }
    public function recipient(): string { return $this->recipient; }
    public function subject(): string { return $this->subject; }
    public function content(): string { return $this->content; }
    public function isSent(): bool { return null !== $this->sentAt; }
    public function markSent(\DateTimeImmutable $at): void { $this->sentAt = $at; }
}
