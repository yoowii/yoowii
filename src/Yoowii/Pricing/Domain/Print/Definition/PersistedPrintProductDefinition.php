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
    private ?int $id = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = false;

    /** @param array<string, array{type: string, required?: bool, allowed_values?: list<string|int|float>, minimum?: int|float|null, maximum?: int|float|null}> $options @param non-empty-list<string> $pricingAxes */
    public function __construct(
        #[ORM\Column(name: 'product_code', type: Types::STRING, length: 64, unique: true)] private readonly string $productCode,
        #[ORM\Column(name: 'schema_version', type: Types::STRING, length: 32)] private readonly string $schemaVersion,
        #[ORM\Column(type: Types::JSON)] private array $options,
        #[ORM\Column(name: 'pricing_axes', type: Types::JSON)] private readonly array $pricingAxes,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function productCode(): string
    {
        return $this->productCode;
    }

    /** @return array<string, array{type: string, required?: bool, allowed_values?: list<string|int|float>, minimum?: int|float|null, maximum?: int|float|null}> */
    public function options(): array
    {
        return $this->options;
    }

    /** @param array<string, array<string, mixed>> $options */
    public function replaceDraftOptions(array $options): void
    {
        if ($this->active) {
            throw new \DomainException('Une définition publiée ne peut pas être modifiée en place.');
        }
        $previous = $this->options;
        $this->options = $options;
        try {
            $this->definition();
        } catch (\Throwable $exception) {
            $this->options = $previous;
            throw $exception;
        }
    }

    /** @return non-empty-list<string> */
    public function pricingAxes(): array
    {
        return $this->pricingAxes;
    }

    /**
     * Public schema used by the storefront. Provider identifiers remain metadata for the server-side connector.
     *
     * @return list<array{code: string, label: string, type: string, values: array<string, string>, area: int, position: int, readonly: bool, default: string|int|null}>
     */
    public function storefrontSchema(): array
    {
        $schema = [];
        foreach ($this->options as $code => $option) {
            if (!is_string($code) || !is_array($option)) {
                continue;
            }
            $valueLabels = [];
            foreach (($option['value_labels'] ?? []) as $value => $label) {
                if ((is_string($value) || is_int($value)) && is_string($label)) {
                    $valueLabels[(string) $value] = $label;
                }
            }
            $schema[] = [
                'code' => $code,
                'label' => is_string($option['label'] ?? null) ? $option['label'] : ucfirst(str_replace('_', ' ', $code)),
                // Provider types only describe the API: forms use canonical Yoowii types.
                'type' => (string) ($option['type'] ?? 'code'),
                'provider_type' => is_string($option['provider_type'] ?? null) ? $option['provider_type'] : null,
                'values' => $valueLabels,
                'area' => isset($option['area']) ? (int) $option['area'] : 1,
                'position' => isset($option['position']) ? (int) $option['position'] : 0,
                'readonly' => (bool) ($option['readonly'] ?? false),
                'default' => is_string($option['default'] ?? null) || is_int($option['default'] ?? null) || is_float($option['default'] ?? null) ? $option['default'] : null,
                'minimum' => isset($option['minimum']) ? (float) $option['minimum'] : null,
                'maximum' => isset($option['maximum']) ? (float) $option['maximum'] : null,
            ];
        }
        usort($schema, static fn (array $left, array $right): int => [$left['area'], $left['position'], $left['code']] <=> [$right['area'], $right['position'], $right['code']]);

        return $schema;
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
            if (!is_string($code) || !is_array($item)) {
                throw new \DomainException('Invalid persisted print option definition.');
            }
            $type = PrintOptionType::tryFrom((string) ($item['type'] ?? ''));
            if (!$type instanceof PrintOptionType) {
                throw new \DomainException(sprintf('Invalid type for print option "%s".', $code));
            }
            $allowed = $item['allowed_values'] ?? [];
            if (!is_array($allowed)) {
                throw new \DomainException('Invalid allowed values.');
            }
            $options[$code] = new PrintOptionDefinition($code, $type, (bool) ($item['required'] ?? true), $allowed, PrintOptionType::Float === $type && isset($item['minimum']) ? (float) $item['minimum'] : (isset($item['minimum']) ? (int) $item['minimum'] : null), PrintOptionType::Float === $type && isset($item['maximum']) ? (float) $item['maximum'] : (isset($item['maximum']) ? (int) $item['maximum'] : null));
        }
        return new PrintProductDefinition($this->productCode, $this->schemaVersion, 'matrix_exact', $options, $this->pricingAxes);
    }
}
