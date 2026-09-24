<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_job_supplier_file_transfer')]
#[ORM\Index(name: 'IDX_282FE5F5E1FD4933', columns: ['submission_id'])]
#[ORM\Index(name: 'IDX_282FE5F564948587', columns: ['print_asset_id'])]
#[ORM\UniqueConstraint(name: 'uniq_print_supplier_file_transfer', columns: ['submission_id', 'print_asset_id'])]
class PrintJobSupplierFileTransfer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore property.unusedType (Assigned by Doctrine after persistence.) */
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $status = 'pending';

    #[ORM\Column(name: 'attempt_count', type: Types::INTEGER, options: ['default' => 0])]
    private int $attemptCount = 0;

    #[ORM\Column(name: 'last_error', type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'submission_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintJobSupplierSubmission $submission,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'print_asset_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly PrintAsset $printAsset,
        #[ORM\Column(name: 'remote_path', type: Types::STRING, length: 1024)]
        private readonly string $remotePath,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function status(): string
    {
        return $this->status;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function submission(): PrintJobSupplierSubmission
    {
        return $this->submission;
    }

    public function printAsset(): PrintAsset
    {
        return $this->printAsset;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function attemptCount(): int
    {
        return $this->attemptCount;
    }

    public function remotePath(): string
    {
        return $this->remotePath;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function markUploaded(\DateTimeImmutable $at, bool $simulated): void
    {
        ++$this->attemptCount;
        $this->status = $simulated ? 'simulated' : 'uploaded';
        $this->lastError = null;
        $this->updatedAt = $at;
    }

    public function markFailed(string $error, \DateTimeImmutable $at): void
    {
        ++$this->attemptCount;
        $this->status = 'failed';
        $this->lastError = mb_substr($error, 0, 4000);
        $this->updatedAt = $at;
    }
}
