<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_supplier_product')]
#[ORM\Index(name: 'IDX_72BE41A72ADD6D8C', columns: ['supplier_id'])]
#[ORM\UniqueConstraint(name: 'uniq_supplier_product_code', columns: ['supplier_id', 'code'])]
class SupplierProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null; // @phpstan-ignore property.unusedType, property.onlyWritten

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(name: 'supplier_id', nullable: false, onDelete: 'RESTRICT')]
        private readonly PrintSupplier $supplier,
        #[ORM\Column(type: Types::STRING, length: 128)]
        private readonly string $code,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
    ) {
        if ('' === trim($this->code)) {
            throw new \InvalidArgumentException('The supplier product code must not be empty.');
        }

        if ('' === trim($this->name)) {
            throw new \InvalidArgumentException('The supplier product name must not be empty.');
        }
    }

    public function supplier(): PrintSupplier
    {
        return $this->supplier;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function rename(string $name): void
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('The supplier product name must not be empty.');
        }

        $this->name = $name;
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
