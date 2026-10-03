<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Definition;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Administrative definition used by the generic print configurator. */
#[ORM\Entity]
#[ORM\Table(name: 'yoowii_print_product_definition')]
class PersistedPrintProductDefinition
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    /** @phpstan-ignore-next-line Doctrine assigns generated identifiers. */
    private ?int $id = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = false;

    /**
     * @param array<string, array{type: string, required?: bool, allowed_values?: list<string|int>, minimum?: int|null, maximum?: int|null}> $options
     * @param non-empty-list<string> $pricingAxes
     */
    public function __construct(
        #[ORM\Column(name: 'product_code', type: Types::STRING, length: 64, unique: true)]
        private readonly string $productCode,
        #[ORM\Column(name: 'schema_version', type: Types::STRING, length: 32)]
        private readonly string $schemaVersion,
        #[ORM\Column(type: Types::JSON)]
        private readonly array $options,
        #[ORM\Column(name: 'pricing_axes', type: Types::JSON)]
        private readonly array $pricingAxes,
    ) {
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function productCode(): string
    {
        return $this->productCode;
    }

    /** @return array<string, array{type: string, required?: bool, allowed_values?: list<string|int>, minimum?: int|null, maximum?: int|null}> */
    public function options(): array
    {
        return $this->options;
    }

    /** @return non-empty-list<string> */
    public function pricingAxes(): array
    {
        return $this->pricingAxes;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function definition(): PrintProductDefinition
    {
        $options = [];
        foreach ($this->options as $code => $item) {
            if ('' === $code) {
                throw new \DomainException('Invalid persisted print option definition.');
            }
            $type = PrintOptionType::tryFrom($item['type']);
            if (!$type instanceof PrintOptionType) {
                throw new \DomainException(sprintf('Invalid type for print option "%s".', $code));
            }
            $allowed = $item['allowed_values'] ?? [];
            $options[$code] = new PrintOptionDefinition($code, $type, (bool) ($item['required'] ?? true), $allowed, isset($item['minimum']) ? (int) $item['minimum'] : null, isset($item['maximum']) ? (int) $item['maximum'] : null);
        }

        return new PrintProductDefinition($this->productCode, $this->schemaVersion, 'matrix_exact', $options, $this->pricingAxes());
    }
}
