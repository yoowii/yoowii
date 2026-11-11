<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job_activity')]
class PrintJobActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    /** @param array<string, scalar|null> $details */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'print_job_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintJob $printJob,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $action,
        #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
        private readonly ?string $actor,
        #[ORM\Column(type: Types::JSON)]
        private readonly array $details,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
        if ('' === trim($action)) {
            throw new \InvalidArgumentException('A print job activity action is required.');
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

    public function action(): string
    {
        return $this->action;
    }

    public function actor(): ?string
    {
        return $this->actor;
    }

    /** @return array<string, scalar|null> */
    public function details(): array
    {
        return $this->details;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
