<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_supplier_pricing_matrix_version')]
#[ORM\UniqueConstraint(name: 'uniq_supplier_pricing_matrix_version', columns: ['supplier_product_id', 'version'])]
class SupplierPricingMatrixVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(type: Types::STRING, length: 16, enumType: PricingMatrixStatus::class)]
    private PricingMatrixStatus $status = PricingMatrixStatus::Draft;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private readonly string $checksum;

    #[ORM\Column(name: 'activated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $activatedAt = null;

    /** @param array<string, mixed> $matrix */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'supplier_product_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly SupplierProduct $supplierProduct,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $version,
        #[ORM\Column(name: 'currency_code', type: Types::STRING, length: 3)]
        private readonly string $currencyCode,
        #[ORM\Column(type: Types::JSON)]
        private readonly array $matrix,
        #[ORM\Column(name: 'effective_from', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $effectiveFrom,
        #[ORM\Column(name: 'imported_at', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $importedAt,
    ) {
        if ('' === trim($this->version)) {
            throw new \InvalidArgumentException('The pricing matrix version must not be empty.');
        }

        if (1 !== preg_match('/^[A-Z]{3}$/D', $this->currencyCode)) {
            throw new \InvalidArgumentException('The currency code must be a three-letter uppercase ISO code.');
        }

        if ([] === $this->matrix) {
            throw new \InvalidArgumentException('The pricing matrix must not be empty.');
        }

        $encodedMatrix = json_encode(self::canonicalize($this->matrix), \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
        $this->checksum = hash('sha256', $encodedMatrix);
    }

    public function supplierProduct(): SupplierProduct
    {
        return $this->supplierProduct;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function importedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function activatedAt(): ?\DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function effectiveFrom(): \DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    /** @return array<string, mixed> */
    public function matrix(): array
    {
        return $this->matrix;
    }

    public function checksum(): string
    {
        return $this->checksum;
    }

    public function status(): PricingMatrixStatus
    {
        return $this->status;
    }

    public function activate(\DateTimeImmutable $at): void
    {
        if (PricingMatrixStatus::Archived === $this->status) {
            throw new \DomainException('An archived pricing matrix cannot be activated.');
        }

        if (PricingMatrixStatus::Active === $this->status) {
            return;
        }

        if ($at < $this->importedAt) {
            throw new \InvalidArgumentException('The activation date cannot be before the import date.');
        }

        $this->status = PricingMatrixStatus::Active;
        $this->activatedAt = $at;
    }

    public function archive(): void
    {
        $this->status = PricingMatrixStatus::Archived;
    }

    public function isSelectableAt(\DateTimeImmutable $at): bool
    {
        return PricingMatrixStatus::Active === $this->status && $at >= $this->effectiveFrom;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function canonicalize(array $value): array
    {
        if (!array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $nestedValue) {
            if (is_array($nestedValue)) {
                $value[$key] = self::canonicalize($nestedValue);

                continue;
            }

            if (
                null !== $nestedValue &&
                !is_bool($nestedValue) &&
                !is_int($nestedValue) &&
                !is_float($nestedValue) &&
                !is_string($nestedValue)
            ) {
                throw new \InvalidArgumentException('The pricing matrix must be JSON-compatible.');
            }

            if (is_float($nestedValue) && !is_finite($nestedValue)) {
                throw new \InvalidArgumentException('The pricing matrix must be JSON-compatible.');
            }
        }

        return $value;
    }
}
