<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Model;

use App\Yoowii\Sourcing\Domain\SupplierCapability;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_supplier')]
#[ORM\UniqueConstraint(name: 'uniq_print_supplier_code', columns: ['code'])]
class PrintSupplier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $capabilities;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = true;

    /** @param list<SupplierCapability> $capabilities */
    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 64)]
        private readonly string $code,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
        #[ORM\Column(name: 'integration_mode', type: Types::STRING, length: 16, enumType: SupplierIntegrationMode::class)]
        private SupplierIntegrationMode $integrationMode,
        array $capabilities = [],
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $this->code)) {
            throw new \InvalidArgumentException('The supplier code must contain lowercase letters, digits, dashes or underscores.');
        }

        if ('' === trim($this->name)) {
            throw new \InvalidArgumentException('The supplier name must not be empty.');
        }

        $this->capabilities = array_values(array_unique(array_map(
            static fn (SupplierCapability $capability): string => $capability->value,
            $capabilities,
        )));
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function integrationMode(): SupplierIntegrationMode
    {
        return $this->integrationMode;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function supports(SupplierCapability $capability): bool
    {
        return in_array($capability->value, $this->capabilities, true);
    }

    /** @return list<SupplierCapability> */
    public function capabilities(): array
    {
        return array_map(
            static fn (string $capability): SupplierCapability => SupplierCapability::from($capability),
            $this->capabilities,
        );
    }

    /** @param list<SupplierCapability> $capabilities */
    public function changeIntegration(
        SupplierIntegrationMode $integrationMode,
        array $capabilities,
    ): void {
        $this->integrationMode = $integrationMode;
        $this->capabilities = array_values(array_unique(array_map(
            static fn (SupplierCapability $capability): string => $capability->value,
            $capabilities,
        )));
    }

    public function rename(string $name): void
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('The supplier name must not be empty.');
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
