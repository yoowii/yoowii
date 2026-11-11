<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job_notification')]
#[ORM\Index(name: 'IDX_9C3D9B1D727301B', columns: ['print_job_id'])]
#[ORM\UniqueConstraint(name: 'uniq_print_notification_fingerprint', columns: ['fingerprint'])]
class PrintJobNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
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
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function printJob(): PrintJob
    {
        return $this->printJob;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function recipient(): string
    {
        return $this->recipient;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isSent(): bool
    {
        return null !== $this->sentAt;
    }

    public function markSent(\DateTimeImmutable $at): void
    {
        $this->sentAt = $at;
    }
}
