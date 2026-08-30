<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'yoowii_print_asset',
    indexes: [new ORM\Index(name: 'IDX_PRINT_ASSET_JOB', columns: ['print_job_id'])],
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'UNIQ_PRINT_ASSET_STORAGE', columns: ['storage_key'])],
)]
class PrintAsset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'print_job_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintJob $printJob,
        #[ORM\Column(type: Types::STRING, length: 32, enumType: PrintAssetType::class)]
        private readonly PrintAssetType $type,
        #[ORM\Column(name: 'original_name', type: Types::STRING, length: 255)]
        private readonly string $originalName,
        #[ORM\Column(name: 'storage_key', type: Types::STRING, length: 512, unique: true)]
        private readonly string $storageKey,
        #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 127)]
        private readonly string $mimeType,
        #[ORM\Column(type: Types::BIGINT)]
        private readonly int $size,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $checksum,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
        if ($size < 1 || '' === trim($storageKey) || '' === trim($checksum)) {
            throw new \InvalidArgumentException('Print asset metadata is invalid.');
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

    public function type(): PrintAssetType
    {
        return $this->type;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function storageKey(): string
    {
        return $this->storageKey;
    }

    public function checksum(): string
    {
        return $this->checksum;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
