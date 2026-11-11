<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Definition;

use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;

final readonly class PrintProductDefinition
{
    /**
     * @param array<string, PrintOptionDefinition> $options
     * @param non-empty-list<string> $pricingAxes
     */
    public function __construct(
        private string $productCode,
        private string $schemaVersion,
        private string $pricingStrategy,
        private array $options,
        private array $pricingAxes,
    ) {
        if (1 !== preg_match('/^[A-Z][A-Z0-9_]*$/D', $this->productCode)) {
            throw new \InvalidArgumentException('A print product code must use uppercase letters, digits and underscores.');
        }

        if ('' === trim($this->schemaVersion)) {
            throw new \InvalidArgumentException('The print product schema version must not be empty.');
        }

        if ('matrix_exact' !== $this->pricingStrategy) {
            throw new \InvalidArgumentException(sprintf('Unsupported print pricing strategy "%s".', $this->pricingStrategy));
        }

        if ([] === $this->options) {
            throw new \InvalidArgumentException('A print product requires options and pricing axes.');
        }

        foreach ($this->options as $optionCode => $option) {
            if ($optionCode !== $option->code()) {
                throw new \InvalidArgumentException('Print option map keys must match their option codes.');
            }
        }

        if (count(array_unique($this->pricingAxes)) !== count($this->pricingAxes)) {
            throw new \InvalidArgumentException('Print pricing axes must not contain duplicates.');
        }

        foreach ($this->pricingAxes as $pricingAxis) {
            if (!isset($this->options[$pricingAxis])) {
                throw new \InvalidArgumentException(sprintf('Unknown print pricing axis "%s".', $pricingAxis));
            }
        }

        foreach ($this->options as $optionCode => $option) {
            if ($option->isRequired() && !in_array($optionCode, $this->pricingAxes, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Required print option "%s" must be a pricing axis for the matrix_exact strategy.',
                    $optionCode,
                ));
            }
        }
    }

    public function productCode(): string
    {
        return $this->productCode;
    }

    public function schemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function pricingStrategy(): string
    {
        return $this->pricingStrategy;
    }

    /** @return non-empty-list<string> */
    public function pricingAxes(): array
    {
        return $this->pricingAxes;
    }

    /** @return list<string> */
    public function csvHeaders(): array
    {
        return [...$this->pricingAxes, 'production_cost', 'shipping_cost'];
    }

    /** @param array<string, mixed> $values */
    public function configure(array $values): PrintConfiguration
    {
        $unknownOptions = array_diff(array_keys($values), array_keys($this->options));

        if ([] !== $unknownOptions) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown print option(s): %s.',
                implode(', ', $unknownOptions),
            ));
        }

        $normalizedValues = [];

        foreach ($this->options as $optionCode => $definition) {
            if (!array_key_exists($optionCode, $values)) {
                if ($definition->isRequired()) {
                    throw new \InvalidArgumentException(sprintf('Required print option "%s" is missing.', $optionCode));
                }

                continue;
            }

            $normalizedValues[$optionCode] = $definition->normalize($values[$optionCode]);
        }

        return new PrintConfiguration(
            $this->productCode,
            $this->schemaVersion,
            $normalizedValues,
            $this->pricingAxes,
        );
    }
}
