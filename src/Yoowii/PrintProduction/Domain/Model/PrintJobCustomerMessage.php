<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job_customer_message')]
#[ORM\Index(name: 'IDX_3730A79D727301B', columns: ['print_job_id'])]
class PrintJobCustomerMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'print_job_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintJob $printJob,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $type,
        #[ORM\Column(type: Types::TEXT)]
        private readonly string $message,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
        if ('' === trim($message)) {
            throw new \InvalidArgumentException('A customer message cannot be empty.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function printJob(): PrintJob
    {
        return $this->printJob;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
