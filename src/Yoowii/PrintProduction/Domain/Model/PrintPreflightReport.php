<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain\Model;

use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_preflight_report')]
#[ORM\UniqueConstraint(name: 'uniq_print_preflight_asset', columns: ['print_asset_id'])]
class PrintPreflightReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(type: Types::STRING, length: 16, enumType: PrintPreflightStatus::class)]
    private PrintPreflightStatus $status = PrintPreflightStatus::Pending;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $report = [];

    #[ORM\Column(name: 'analysed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $analysedAt = null;

    public function __construct(
        #[ORM\OneToOne]
        #[ORM\JoinColumn(name: 'print_asset_id', nullable: false, onDelete: 'CASCADE')]
        private readonly PrintAsset $printAsset,
        #[ORM\Column(name: 'asset_checksum', type: Types::STRING, length: 64)]
        private readonly string $assetChecksum,
        #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): ?int { return $this->id; }
    public function printAsset(): PrintAsset { return $this->printAsset; }
    public function assetChecksum(): string { return $this->assetChecksum; }
    public function status(): PrintPreflightStatus { return $this->status; }
    /** @return array<string, mixed> */
    public function report(): array { return $this->report; }
    public function analysedAt(): ?\DateTimeImmutable { return $this->analysedAt; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @param array<string, mixed> $report */
    public function complete(PrintPreflightStatus $status, array $report, \DateTimeImmutable $at): void
    {
        if (PrintPreflightStatus::Pending === $status) {
            throw new \InvalidArgumentException('A completed preflight report must have a final status.');
        }
        $this->status = $status;
        $this->report = $report;
        $this->analysedAt = $at;
    }

    public function restart(): void
    {
        $this->status = PrintPreflightStatus::Pending;
        $this->report = [];
        $this->analysedAt = null;
    }
}
