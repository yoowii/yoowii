<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_supplier_route')]
#[ORM\Index(name: 'IDX_54348EDC8241E9B7', columns: ['supplier_product_id'])]
#[ORM\Index(name: 'idx_supplier_route_lookup', columns: ['yoowii_product_code', 'active', 'priority'])]
class SupplierRoute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    public function __construct(
        #[ORM\Column(name: 'yoowii_product_code', type: Types::STRING, length: 64)]
        private readonly string $yoowiiProductCode,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'supplier_product_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly SupplierProduct $supplierProduct,
        #[ORM\Column(type: Types::INTEGER)]
        private int $priority,
        #[ORM\Column(name: 'effective_from', type: Types::DATETIME_IMMUTABLE)]
        private readonly \DateTimeImmutable $effectiveFrom,
        #[ORM\Column(name: 'effective_until', type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private readonly ?\DateTimeImmutable $effectiveUntil = null,
    ) {
        if ('' === trim($this->yoowiiProductCode)) {
            throw new \InvalidArgumentException('The Yoowii product code must not be empty.');
        }

        if ($this->priority < 1) {
            throw new \InvalidArgumentException('The supplier route priority must be greater than zero.');
        }

        if (null !== $this->effectiveUntil && $this->effectiveUntil <= $this->effectiveFrom) {
            throw new \InvalidArgumentException('The route end date must be after its start date.');
        }
    }

    public function yoowiiProductCode(): string
    {
        return $this->yoowiiProductCode;
    }

    public function supplierProduct(): SupplierProduct
    {
        return $this->supplierProduct;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function isEligibleFor(string $productCode, \DateTimeImmutable $at): bool
    {
        return $this->active &&
            $this->supplierProduct->isActive() &&
            $this->supplierProduct->supplier()->isActive() &&
            $this->yoowiiProductCode === $productCode &&
            $at >= $this->effectiveFrom &&
            (null === $this->effectiveUntil || $at < $this->effectiveUntil);
    }

    public function changePriority(int $priority): void
    {
        if ($priority < 1) {
            throw new \InvalidArgumentException('The supplier route priority must be greater than zero.');
        }

        $this->priority = $priority;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }
}
