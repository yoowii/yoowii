<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;

/** Applies supplier availability without ever interpreting availability flags as provider values. */
final class RealisaprintAvailabilityFallback
{
    /**
     * @param array<string, string|int|float> $values
     * @param array{availability: array<string, list<string>>, current: array<string, string>} $state
     * @return array<string, string|int|float>
     */
    public function apply(array $values, array $state, PersistedPrintProductDefinition $definition): array
    {
        $schemas = [];
        foreach ($definition->options() as $code => $option) {
            if (is_string($code) && is_array($option)) {
                $schemas[] = ['code' => $code] + $option;
            }
        }

        return $this->applyToSchema($values, $state, $schemas, $definition->pricingAxes());
    }

    /** @param array<string, string|int|float> $values @param array{availability: array<string, list<string>>, current: array<string, string>} $state @param list<array<string, mixed>> $schemas @param list<string> $axes
     * @return array<string, string|int|float> */
    public function applyToSchema(array $values, array $state, array $schemas, array $axes): array
    {
        $options = [];
        foreach ($schemas as $schema) {
            if (is_string($schema['code'] ?? null)) {
                $options[$schema['code']] = $schema;
            }
        }
        foreach ($axes as $axis) {
            if (!array_key_exists($axis, $values) || '' === trim((string) $values[$axis])) {
                throw new \DomainException(sprintf('La configuration a changé chez le fournisseur ; choisissez une nouvelle valeur pour « %s ».', $this->label($options, $axis)));
            }
            $available = $state['availability'][$axis] ?? null;
            if (null === $available || in_array((string) $values[$axis], $available, true)) {
                continue;
            }
            if ([] === $available) {
                throw new \DomainException(sprintf('La configuration a changé chez le fournisseur ; choisissez une nouvelle valeur pour « %s ».', $this->label($options, $axis)));
            }
            $option = $options[$axis] ?? [];
            $default = $option['default'] ?? null;
            if (is_scalar($default) && in_array((string) $default, $available, true)) {
                $values[$axis] = (string) $default;
                continue;
            }
            $providerCurrent = $state['current'][$axis] ?? null;
            if (is_string($providerCurrent) && in_array($providerCurrent, $available, true)) {
                $values[$axis] = $providerCurrent;
                continue;
            }
            foreach (($option['allowed_values'] ?? []) as $candidate) {
                if (is_scalar($candidate) && in_array((string) $candidate, $available, true)) {
                    $values[$axis] = (string) $candidate;
                    continue 2;
                }
            }
            throw new \DomainException(sprintf('La configuration a changé chez le fournisseur ; choisissez une nouvelle valeur pour « %s ».', $this->label($options, $axis)));
        }

        return $values;
    }

    /** @param array<string, array<string, mixed>> $options */
    private function label(array $options, string $axis): string
    {
        $option = $options[$axis] ?? [];

        return is_string($option['label'] ?? null) ? $option['label'] : ucfirst(str_replace('_', ' ', $axis));
    }
}
