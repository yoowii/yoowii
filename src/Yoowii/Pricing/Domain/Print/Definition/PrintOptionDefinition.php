<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Definition;

final readonly class PrintOptionDefinition
{
    /**
     * @param list<string|int> $allowedValues
     */
    public function __construct(
        private string $code,
        private PrintOptionType $type,
        private bool $required = true,
        private array $allowedValues = [],
        private ?int $minimum = null,
        private ?int $maximum = null,
    ) {
        if (1 !== preg_match('/^[a-z][a-z0-9_]*$/D', $this->code)) {
            throw new \InvalidArgumentException('A print option code must use lowercase letters, digits and underscores.');
        }

        if (null !== $this->minimum && null !== $this->maximum && $this->minimum > $this->maximum) {
            throw new \InvalidArgumentException(sprintf('Print option "%s" has an invalid numeric range.', $this->code));
        }

        foreach ($this->allowedValues as $allowedValue) {
            $this->assertValueType($allowedValue);
        }
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function normalize(mixed $value): string|int
    {
        $normalizedValue = match ($this->type) {
            PrintOptionType::Code => $this->normalizeCode($value),
            PrintOptionType::Integer => $this->normalizeInteger($value),
        };

        if ([] !== $this->allowedValues && !in_array($normalizedValue, $this->allowedValues, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Print option "%s" contains unsupported value "%s".',
                $this->code,
                (string) $normalizedValue,
            ));
        }

        if (is_int($normalizedValue) && null !== $this->minimum && $normalizedValue < $this->minimum) {
            throw new \InvalidArgumentException(sprintf(
                'Print option "%s" must be greater than or equal to %d.',
                $this->code,
                $this->minimum,
            ));
        }

        if (is_int($normalizedValue) && null !== $this->maximum && $normalizedValue > $this->maximum) {
            throw new \InvalidArgumentException(sprintf(
                'Print option "%s" must be less than or equal to %d.',
                $this->code,
                $this->maximum,
            ));
        }

        return $normalizedValue;
    }

    private function normalizeCode(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Print option "%s" must be a string code.', $this->code));
        }

        $value = strtolower(trim($value));

        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $value)) {
            throw new \InvalidArgumentException(sprintf('Print option "%s" must be a canonical lowercase code.', $this->code));
        }

        return $value;
    }

    private function normalizeInteger(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || 1 !== preg_match('/^-?\d+$/D', trim($value))) {
            throw new \InvalidArgumentException(sprintf('Print option "%s" must be an integer.', $this->code));
        }

        $integer = filter_var(trim($value), FILTER_VALIDATE_INT);

        if (false === $integer) {
            throw new \InvalidArgumentException(sprintf('Print option "%s" is outside the supported integer range.', $this->code));
        }

        return $integer;
    }

    private function assertValueType(string|int $value): void
    {
        if (PrintOptionType::Code === $this->type && !is_string($value)) {
            throw new \InvalidArgumentException(sprintf('Allowed values for print option "%s" must be strings.', $this->code));
        }

        if (PrintOptionType::Integer === $this->type && !is_int($value)) {
            throw new \InvalidArgumentException(sprintf('Allowed values for print option "%s" must be integers.', $this->code));
        }
    }
}
