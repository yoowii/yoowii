<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_realisaprint_mapping_validation')]
#[ORM\Index(name: 'idx_realisaprint_mapping_validation', columns: ['mapping_id', 'checked_at'])]
class RealisaprintMappingValidation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** @param list<string> $coverageErrors */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'mapping_id', nullable: false, onDelete: 'CASCADE')]
        private readonly SupplierProductMappingVersion $mapping,
        #[ORM\Column(name: 'coverage_complete', type: Types::BOOLEAN)]
        private readonly bool $coverageComplete,
        #[ORM\Column(name: 'quote_passed', type: Types::BOOLEAN)]
        private readonly bool $quotePassed,
        #[ORM\Column(name: 'supplier_cost', type: Types::INTEGER, nullable: true)]
        private readonly ?int $supplierCost,
        #[ORM\Column(name: 'supplier_base_cost', type: Types::INTEGER, nullable: true)]
        private readonly ?int $supplierBaseCost,
        #[ORM\Column(name: 'supplier_options_cost', type: Types::INTEGER, nullable: true)]
        private readonly ?int $supplierOptionsCost,
        #[ORM\Column(name: 'test_configuration', type: Types::JSON)]
        private readonly array $testConfiguration,
        #[ORM\Column(name: 'test_fingerprint', type: Types::STRING, length: 64)]
        private readonly string $testFingerprint,
        #[ORM\Column(name: 'coverage_errors', type: Types::JSON)]
        private readonly array $coverageErrors,
        #[ORM\Column(name: 'technical_detail', type: Types::STRING, length: 280, nullable: true)]
        private readonly ?string $technicalDetail,
        #[ORM\Column(name: 'checked_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $checkedAt,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function mapping(): SupplierProductMappingVersion
    {
        return $this->mapping;
    }

    public function coverageComplete(): bool
    {
        return $this->coverageComplete;
    }

    public function quotePassed(): bool
    {
        return $this->quotePassed;
    }

    public function supplierCost(): ?int
    {
        return $this->supplierCost;
    }

    public function supplierBaseCost(): ?int
    {
        return $this->supplierBaseCost;
    }

    public function supplierOptionsCost(): ?int
    {
        return $this->supplierOptionsCost;
    }

    /**  array<string, string|int|float> */
    public function testConfiguration(): array
    {
        return $this->testConfiguration;
    }

    public function testFingerprint(): string
    {
        return $this->testFingerprint;
    }

    /** @return list<string> */
    public function coverageErrors(): array
    {
        return $this->coverageErrors;
    }

    public function technicalDetail(): ?string
    {
        return $this->technicalDetail;
    }

    public function checkedAt(): \DateTimeImmutable
    {
        return $this->checkedAt;
    }
}
