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

    /**
     * @param array<string, array<string, mixed>> $options
     * @param non-empty-list<string> $pricingAxes
     */
    public function __construct(
        #[ORM\Column(name: 'product_code', type: Types::STRING, length: 64)]
        private readonly string $productCode,
        #[ORM\Column(name: 'schema_version', type: Types::STRING, length: 32)]
        private readonly string $schemaVersion,
        #[ORM\Column(type: Types::JSON)]
        private array $options,
        #[ORM\Column(name: 'pricing_axes', type: Types::JSON)]
        private array $pricingAxes,
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

    /** @return array<string, array<string, mixed>> */
    public function options(): array
    {
        return $this->options;
    }

    /** @param array<string, array<string, mixed>> $options */
    public function replaceDraftOptions(array $options): void
    {
        $this->replaceDraftDefinition($options, $this->pricingAxes);
    }

    /**
     * @param array<string, array<string, mixed>> $options
     * @param non-empty-list<string> $pricingAxes
     */
    public function replaceDraftDefinition(array $options, array $pricingAxes): void
    {
        if ($this->active) {
            throw new \DomainException('Une définition publiée ne peut pas être modifiée en place.');
        }
        $previous = $this->options;
        $previousAxes = $this->pricingAxes;
        $this->options = $options;
        $this->pricingAxes = $pricingAxes;

        try {
            $this->definition();
        } catch (\Throwable $exception) {
            $this->options = $previous;
            $this->pricingAxes = $previousAxes;

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
     * @return list<array{code: string, label: string, type: string, values: array<string, string>, area: int, position: int, readonly: bool, default: string|int|float|null, depends_on: array{option: string, value: string}|null}>
     */
    public function storefrontSchema(): array
    {
        $schema = [];
        foreach ($this->options as $code => $option) {
            $valueLabels = [];
            $labels = $option['value_labels'] ?? null;
            foreach (is_array($labels) ? $labels : [] as $value => $label) {
                if ((is_string($value) || is_int($value)) && is_string($label)) {
                    $valueLabels[(string) $value] = $label;
                }
            }
            $parent = str_ends_with($code, '_zone') ? substr($code, 0, -strlen('_zone')) : null;
            $allowed = $option['allowed_values'] ?? null;
            $area = $option['area'] ?? null;
            $position = $option['position'] ?? null;
            $minimum = $option['minimum'] ?? null;
            $maximum = $option['maximum'] ?? null;
            $schema[] = [
                'code' => $code,
                'label' => is_string($option['label'] ?? null) ? $option['label'] : ucfirst(str_replace('_', ' ', $code)),
                // Provider types only describe the API: forms use canonical Yoowii types.
                'type' => 'checkbox' === ($option['provider_type'] ?? null) ? 'checkbox' : (is_string($option['type'] ?? null) ? $option['type'] : 'code'),
                'provider_type' => is_string($option['provider_type'] ?? null) ? $option['provider_type'] : null,
                'values' => $valueLabels,
                'allowed_values' => array_values(array_filter(is_array($allowed) ? $allowed : [], static fn (mixed $value): bool => is_string($value) || is_int($value))),
                'area' => is_scalar($area) ? (int) $area : 1,
                'position' => is_scalar($position) ? (int) $position : 0,
                'readonly' => (bool) ($option['readonly'] ?? false),
                'default' => is_string($option['default'] ?? null) || is_int($option['default'] ?? null) || is_float($option['default'] ?? null) ? $option['default'] : null,
                'minimum' => is_numeric($minimum) ? (float) $minimum : null,
                'maximum' => is_numeric($maximum) ? (float) $maximum : null,
                'depends_on' => null !== $parent ? $this->yesNoDependency($parent) : null,
            ];
        }
        usort($schema, static fn (array $left, array $right): int => [
            'delai' === $left['code'] ? 1 : 0,
            $left['area'],
            $left['position'],
            $left['code'],
        ] <=> [
            'delai' === $right['code'] ? 1 : 0,
            $right['area'],
            $right['position'],
            $right['code'],
        ]);

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

    /** Creates an inactive, independently publishable revision of this definition. */
    public function revise(): self
    {
        return new self($this->productCode, $this->schemaVersion, $this->options, $this->pricingAxes);
    }

    public function definition(): PrintProductDefinition
    {
        $options = [];
        foreach ($this->options as $code => $item) {
            $rawType = $item['type'] ?? null;
            $type = PrintOptionType::tryFrom('checkbox' === ($item['provider_type'] ?? null) ? 'checkbox' : (is_string($rawType) ? $rawType : ''));
            if (!$type instanceof PrintOptionType) {
                throw new \DomainException(sprintf('Invalid type for print option "%s".', $code));
            }
            $allowed = $item['allowed_values'] ?? [];
            if (!is_array($allowed) || !array_is_list($allowed)) {
                throw new \DomainException('Invalid allowed values.');
            }
            foreach ($allowed as $value) {
                if (!is_string($value) && !is_int($value)) {
                    throw new \DomainException(sprintf('Invalid allowed value for print option "%s".', $code));
                }
            }
            /** @var list<string|int> $allowed */
            $min = $item['minimum'] ?? null;
            $max = $item['maximum'] ?? null;
            if (null !== $min && !is_numeric($min)) {
                throw new \DomainException(sprintf('Invalid minimum for print option "%s".', $code));
            }
            if (null !== $max && !is_numeric($max)) {
                throw new \DomainException(sprintf('Invalid maximum for print option "%s".', $code));
            }
            $minimum = null === $min ? null : (PrintOptionType::Float === $type ? (float) $min : (int) $min);
            $maximum = null === $max ? null : (PrintOptionType::Float === $type ? (float) $max : (int) $max);
            $options[$code] = new PrintOptionDefinition($code, $type, (bool) ($item['required'] ?? true), $allowed, $minimum, $maximum);
        }

        return new PrintProductDefinition($this->productCode, $this->schemaVersion, 'matrix_exact', $options, $this->pricingAxes);
    }

    /** @return array{option: string, value: string}|null */
    private function yesNoDependency(string $parent): ?array
    {
        $parentDefinition = $this->options[$parent] ?? null;
        if (!is_array($parentDefinition)) {
            return null;
        }
        $matches = ['oui' => [], 'non' => []];
        $labels = $parentDefinition['value_labels'] ?? null;
        foreach (is_array($labels) ? $labels : [] as $canonical => $label) {
            if (!is_string($label) || (!is_string($canonical) && !is_int($canonical))) {
                continue;
            }
            $meaning = mb_strtolower(trim($label));
            if (isset($matches[$meaning])) {
                $matches[$meaning][] = (string) $canonical;
            }
        }
        // Definitions without labels may still publish canonical oui/non values.
        if ([] === $matches['oui'] && [] === $matches['non']) {
            $allowed = $parentDefinition['allowed_values'] ?? null;
            foreach (is_array($allowed) ? $allowed : [] as $canonical) {
                if (!is_string($canonical) && !is_int($canonical)) {
                    continue;
                }
                $meaning = mb_strtolower(trim((string) $canonical));
                if (isset($matches[$meaning])) {
                    $matches[$meaning][] = (string) $canonical;
                }
            }
        }
        if (1 !== count(array_unique($matches['oui'])) || 1 !== count(array_unique($matches['non']))) {
            return null;
        }

        return ['option' => $parent, 'value' => $matches['oui'][0]];
    }
}
