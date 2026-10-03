<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A read-only, locally cached Realisaprint catalogue entry. */
#[ORM\Entity]
#[ORM\Table(name: 'yoowii_realisaprint_catalog_product')]
#[ORM\UniqueConstraint(name: 'uniq_realisaprint_catalog_product', columns: ['provider_product_id'])]
class RealisaprintCatalogProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore-next-line Doctrine assigns generated identifiers. */
    private ?int $id = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $configuration = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $archived = false;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(name: 'configuration_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $configurationSyncedAt = null;

    public function __construct(
        #[ORM\Column(name: 'provider_product_id', type: Types::STRING, length: 64)]
        private readonly string $providerProductId,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
        \DateTimeImmutable $seenAt,
    ) {
        $this->lastSeenAt = $seenAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function providerProductId(): string
    {
        return $this->providerProductId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function configurationSyncedAt(): ?\DateTimeImmutable
    {
        return $this->configurationSyncedAt;
    }

    public function archived(): bool
    {
        return $this->archived;
    }

    /** @return array<string, mixed>|null */
    public function configuration(): ?array
    {
        return $this->configuration;
    }

    public function refresh(string $name, \DateTimeImmutable $seenAt): void
    {
        $this->name = $name;
        $this->lastSeenAt = $seenAt;
        $this->archived = false;
    }

    /** @param array<string, mixed> $configuration */
    public function refreshConfiguration(array $configuration, \DateTimeImmutable $at): void
    {
        $this->configuration = $configuration;
        $this->configurationSyncedAt = $at;
    }

    public function archive(): void
    {
        $this->archived = true;
    }
}
