<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

/** Applies published numeric minimums before a configuration reaches its domain definition. */
final class PublishedNumericMinimums
{
    /**
     * @param list<array<string, mixed>> $schemas
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public function apply(array $schemas, array $values): array
    {
        foreach ($schemas as $schema) {
            $code = $schema['code'] ?? null;
            $type = $schema['type'] ?? null;
            $minimum = $schema['minimum'] ?? null;
            if (!is_string($code) || !in_array($type, ['integer', 'float'], true) || !is_numeric($minimum)) {
                continue;
            }

            $value = $values[$code] ?? null;
            $normalized = is_string($value) ? str_replace(',', '.', trim($value)) : $value;
            $valid = is_int($normalized) || is_float($normalized) || (is_string($normalized) && is_numeric($normalized));
            if ('integer' === $type && (!is_int($normalized) && (!is_string($normalized) || 1 !== preg_match('/^-?\d+$/D', $normalized)))) {
                $valid = false;
            }
            if (!$valid || !is_finite((float) $normalized) || (float) $normalized < (float) $minimum) {
                $values[$code] = 'integer' === $type ? (int) $minimum : (float) $minimum;
            }
        }

        return $values;
    }
}
