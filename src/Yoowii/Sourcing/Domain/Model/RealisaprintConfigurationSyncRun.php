<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncMode;
use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_realisaprint_configuration_sync_run')]
#[ORM\UniqueConstraint(name: 'uniq_realisaprint_configuration_sync_active', columns: ['active_lock'])]
class RealisaprintConfigurationSyncRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(type: Types::STRING, length: 12, enumType: RealisaprintConfigurationSyncMode::class)]
    private readonly RealisaprintConfigurationSyncMode $mode;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: RealisaprintConfigurationSyncStatus::class)]
    private RealisaprintConfigurationSyncStatus $status = RealisaprintConfigurationSyncStatus::Pending;

    #[ORM\Column(name: 'active_lock', type: Types::STRING, length: 64, nullable: true)]
    private ?string $activeLock = 'catalog-configurations';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var list<array{product_id: int, provider_product_id: string, name: string, outcome: string, detail?: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $report = [];

    public function __construct(RealisaprintConfigurationSyncMode $mode, \DateTimeImmutable $createdAt)
    {
        $this->mode = $mode;
        $this->createdAt = $createdAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function mode(): RealisaprintConfigurationSyncMode
    {
        return $this->mode;
    }

    public function status(): RealisaprintConfigurationSyncStatus
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** @return list<array{product_id: int, provider_product_id: string, name: string, outcome: string, detail?: string}> */
    public function report(): array
    {
        return $this->report;
    }

    public function isActive(): bool
    {
        return null !== $this->activeLock;
    }

    public function start(\DateTimeImmutable $at): void
    {
        if (RealisaprintConfigurationSyncStatus::Pending === $this->status) {
            $this->status = RealisaprintConfigurationSyncStatus::Running;
            $this->startedAt = $at;
        }
    }

    /** @param array{product_id: int, provider_product_id: string, name: string, outcome: string, detail?: string} $entry */
    public function addReportEntry(array $entry): void
    {
        $this->report[] = $entry;
    }

    public function hasReportedProduct(int $productId): bool
    {
        foreach ($this->report as $entry) {
            if ($entry['product_id'] === $productId) {
                return true;
            }
        }

        return false;
    }

    public function finish(\DateTimeImmutable $at, bool $hasErrors): void
    {
        $this->status = $hasErrors ? RealisaprintConfigurationSyncStatus::CompletedWithErrors : RealisaprintConfigurationSyncStatus::Completed;
        $this->finishedAt = $at;
        $this->activeLock = null;
    }

    /** @return array{selected: int, synchronized: int, skipped: int, failed: int} */
    public function summary(): array
    {
        $summary = ['selected' => count($this->report), 'synchronized' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->report as $entry) {
            if ('synchronized' === $entry['outcome']) {
                ++$summary['synchronized'];
            }
            if ('skipped' === $entry['outcome']) {
                ++$summary['skipped'];
            }
            if ('failed' === $entry['outcome']) {
                ++$summary['failed'];
            }
        }

        return $summary;
    }
}
