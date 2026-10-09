<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;

/** Resolves only the selections that are honest to show on first storefront render. */
final class StorefrontInitialConfiguratorValues
{
    /**
     * @param list<array<string, mixed>> $schemas
     * @param array<string, array{value: string|int|float, label: string}> $fixedValues
     * @param array<string, mixed>|null $initialDisplayState
     * @return array<string, string|int|float>
     */
    public function resolve(PrintProductDefinition $definition, array $schemas, array $fixedValues, ?array $initialDisplayState): array
    {
        $visibility = is_array($initialDisplayState['visibility'] ?? null) ? $initialDisplayState['visibility'] : [];
        $availability = is_array($initialDisplayState['availability'] ?? null) ? $initialDisplayState['availability'] : [];
        $values = [];

        foreach ($schemas as $schema) {
            $code = $schema['code'] ?? null;
            if (!is_string($code) || isset($fixedValues[$code]) || true === ($schema['fixed'] ?? false) || false === ($visibility[$code] ?? true)) {
                continue;
            }
            if (in_array($schema['type'] ?? null, ['integer', 'float', 'text'], true)) {
                continue;
            }
            $allowed = $schema['allowed_values'] ?? array_keys(is_array($schema['values'] ?? null) ? $schema['values'] : []);
            if (!is_array($allowed)) {
                continue;
            }
            $allowed = array_values(array_filter($allowed, static fn (mixed $value): bool => is_string($value) || is_int($value) || is_float($value)));
            $available = $availability[$code] ?? null;
            if (is_array($available)) {
                $allowed = array_values(array_filter($allowed, static fn (string|int|float $value): bool => in_array((string) $value, $available, true)));
            }
            if ([] === $allowed) {
                continue;
            }
            $non = $this->nonValue($schema, $allowed);
            if (null !== $non) {
                $values[$code] = $non;
                continue;
            }
            if (1 === count($allowed)) {
                $values[$code] = $allowed[0];
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $schema @param list<string|int|float> $allowed */
    private function nonValue(array $schema, array $allowed): string|int|float|null
    {
        if ('checkbox' !== ($schema['type'] ?? null)) {
            return null;
        }
        foreach ($allowed as $value) {
            $label = is_string($schema['values'][(string) $value] ?? null) ? $schema['values'][(string) $value] : $value;
            if ('non' === mb_strtolower(trim((string) $label))) {
                return $value;
            }
        }

        return null;
    }
}
