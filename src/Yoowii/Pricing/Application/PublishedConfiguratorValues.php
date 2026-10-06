<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;

/** Resolves published display rules before a configuration can reach a supplier. */
final class PublishedConfiguratorValues
{
    /** @param list<array<string, mixed>> $schemas @param array<string, mixed> $values @param array<string, array{value: string|int|float, label: string}> $fixed */
    public function resolve(PrintProductDefinition $definition, array $schemas, array $values, array $fixed = []): array
    {
        foreach ($fixed as $code => $field) {
            $values[$code] = $field['value'];
        }
        foreach ($schemas as $schema) {
            $code = $schema['code'] ?? null;
            if (!is_string($code) || array_key_exists($code, $values) || array_key_exists($code, $fixed)) {
                continue;
            }
            // Disabled dynamic fields are deliberately absent from FormData. Their
            // value is selected exclusively from the published server-side default.
            $default = $schema['default'] ?? null;
            if (null === $default || '' === $default) {
                throw new \DomainException(sprintf('La définition publiée ne fournit pas de valeur sûre pour l’axe caché « %s ».', $code));
            }
            $values[$code] = $default;
        }

        $resolved = $definition->configure($values)->toArray();
        foreach ($definition->pricingAxes() as $axis) {
            if (!array_key_exists($axis, $resolved) || '' === trim((string) $resolved[$axis])) {
                throw new \DomainException(sprintf('L’axe de prix « %s » ne possède aucune valeur publiée sûre.', $axis));
            }
        }

        return $resolved;
    }

    /** @param array<string, mixed> $schema @param array<string, mixed> $values */
    public function isVisible(array $schema, array $values): bool
    {
        if ((int) ($schema['position'] ?? 0) <= 0 || true === ($schema['fixed'] ?? false)) {
            return false;
        }
        $dependency = $schema['depends_on'] ?? null;
        if (!is_array($dependency)) {
            return true;
        }

        return array_key_exists($dependency['option'] ?? '', $values)
            && (string) $values[$dependency['option']] === (string) ($dependency['value'] ?? '');
    }

    /** @param list<array<string, mixed>> $schemas @param array<string, mixed> $values */
    public function visible(array $schemas, array $values): array
    {
        $visible = array_values(array_filter($schemas, fn (array $schema): bool => $this->isVisible($schema, $values)));
        usort($visible, static fn (array $left, array $right): int => [
            (int) ($left['area'] ?? 1),
            (int) ($left['position'] ?? 0),
            (string) ($left['code'] ?? ''),
        ] <=> [
            (int) ($right['area'] ?? 1),
            (int) ($right['position'] ?? 0),
            (string) ($right['code'] ?? ''),
        ]);

        return $visible;
    }
}
