<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_supplier_product_mapping_version')]
#[ORM\Index(name: 'IDX_D23518858241E9B7', columns: ['supplier_product_id'])]
#[ORM\UniqueConstraint(
    name: 'uniq_supplier_product_mapping_version',
    columns: ['supplier_product_id', 'yoowii_product_code', 'version'],
)]
class SupplierProductMappingVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    /**
     * @param array<string, mixed> $configurationMapping
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'supplier_product_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly SupplierProduct $supplierProduct,
        #[ORM\Column(name: 'yoowii_product_code', type: Types::STRING, length: 64)]
        private readonly string $yoowiiProductCode,
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $version,
        #[ORM\Column(name: 'configuration_mapping', type: Types::JSON)]
        private readonly array $configurationMapping,
        #[ORM\Column(name: 'effective_from', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $effectiveFrom,
        #[ORM\Column(name: 'effective_until', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private readonly ?\DateTimeImmutable $effectiveUntil = null,
    ) {
        if ('' === trim($this->yoowiiProductCode)) {
            throw new \InvalidArgumentException('The Yoowii product code must not be empty.');
        }

        if ('' === trim($this->version)) {
            throw new \InvalidArgumentException('The mapping version must not be empty.');
        }

        if (null !== $this->effectiveUntil && $this->effectiveUntil <= $this->effectiveFrom) {
            throw new \InvalidArgumentException('The mapping end date must be after its start date.');
        }

        self::assertJsonCompatible($this->configurationMapping, 'configuration_mapping');
    }

    public function supplierProduct(): SupplierProduct
    {
        return $this->supplierProduct;
    }

    public function yoowiiProductCode(): string
    {
        return $this->yoowiiProductCode;
    }

    public function version(): string
    {
        return $this->version;
    }

    /** @return array<string, mixed> */
    public function configurationMapping(): array
    {
        return $this->configurationMapping;
    }

    public function isEffectiveAt(\DateTimeImmutable $at): bool
    {
        return $this->active &&
            $at >= $this->effectiveFrom &&
            (null === $this->effectiveUntil || $at < $this->effectiveUntil);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /** @param array<array-key, mixed> $value */
    private static function assertJsonCompatible(array $value, string $path): void
    {
        foreach ($value as $key => $nestedValue) {
            $nestedPath = sprintf('%s.%s', $path, (string) $key);

            if (null === $nestedValue || is_bool($nestedValue) || is_int($nestedValue) || is_string($nestedValue)) {
                continue;
            }

            if (is_float($nestedValue) && is_finite($nestedValue)) {
                continue;
            }

            if (is_array($nestedValue)) {
                self::assertJsonCompatible($nestedValue, $nestedPath);

                continue;
            }

            throw new \InvalidArgumentException(sprintf('The value at "%s" is not JSON-compatible.', $nestedPath));
        }
    }
}
