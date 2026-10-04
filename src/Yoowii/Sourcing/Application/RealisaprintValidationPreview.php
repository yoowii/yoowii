<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;

/** Builds a read-only explanation of the sample quote and mapping coverage. */
final class RealisaprintValidationPreview
{
    /**
     * @param array<string, mixed> $catalog
     * @param array<string, string|int> $sampleConfiguration
     *
     * @return array{stock: string, stock_label: string, stock_valid: bool, rows: list<array{label: string, option: string, sample: string, variable: string, provider_label: string, provider_value: string, mapped: int, total: int, covered: bool}>, covered: bool}
     */
    public function build(PersistedPrintProductDefinition $definition, SupplierProductMappingVersion $mapping, array $catalog, array $sampleConfiguration): array
    {
        $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
        $provider = is_array($provider) ? $provider : [];
        $rules = is_array($provider['variables'] ?? null) ? $provider['variables'] : [];
        $variables = is_array($catalog['variables'] ?? null) ? $catalog['variables'] : [];
        $stocks = is_array($catalog['stocks'] ?? null) ? $catalog['stocks'] : [];
        $stock = is_scalar($provider['stock'] ?? null) ? (string) $provider['stock'] : '';
        $stockValid = '' !== $stock && array_key_exists($stock, $stocks);
        $byOption = [];
        foreach ($rules as $id => $rule) {
            if (is_string($id) && is_array($rule) && is_string($rule['option'] ?? null)) {
                $byOption[$rule['option']] = ['id' => $id, 'rule' => $rule];
            }
        }
        $rows = [];
        $covered = $stockValid && count($rules) === count($variables);
        foreach ($definition->options() as $code => $option) {
            if (!is_string($code) || !is_array($option)) {
                continue;
            }
            $entry = $byOption[$code] ?? [];
            $id = $entry['id'] ?? '';
            $rule = $entry['rule'] ?? [];
            $source = is_array($variables[$id] ?? null) ? $variables[$id] : [];
            $values = is_array($rule['values'] ?? null) ? $rule['values'] : [];
            $sourceValues = $source['values'] ?? false;
            $allowed = is_array($option['allowed_values'] ?? null) ? $option['allowed_values'] : [];
            $sample = $sampleConfiguration[$code] ?? null;
            $mapped = 0;
            $fixed = 'text' === ($source['type'] ?? null) && true === ($source['readonly'] ?? false)
                && false === $sourceValues && is_string($source['default'] ?? null) && '' !== trim($source['default'])
                && 1 === count($allowed) && ($values[(string) $allowed[0]] ?? null) === (string) $source['default'];
            foreach ($allowed as $value) {
                $providerValue = $values[(string) $value] ?? null;
                if ($fixed || (is_scalar($providerValue) && is_array($sourceValues) && array_key_exists((string) $providerValue, $sourceValues))) {
                    ++$mapped;
                }
            }
            $free = [] === $allowed && in_array($option['type'] ?? null, ['integer', 'text'], true) && (false === $sourceValues || null === $sourceValues);
            $rowCovered = null !== $sample && '' !== $id && [] !== $source
                && ($option['provider_variable'] ?? null) === $id
                && ($free || $fixed || ([] !== $allowed && $mapped === count($allowed) && count($values) === count($allowed)));
            $covered = $covered && $rowCovered;
            $providerValue = $free ? $sample : ($values[(string) $sample] ?? null);
            $labels = is_array($option['value_labels'] ?? null) ? $option['value_labels'] : [];
            $rows[] = [
                'label' => is_string($option['label'] ?? null) ? $option['label'] : $code,
                'option' => $code,
                'sample' => null === $sample ? '—' : (string) ($labels[(string) $sample] ?? $sample),
                'variable' => $id,
                'provider_label' => is_string($source['name'] ?? null) ? $source['name'] : '',
                'provider_value' => is_scalar($providerValue) ? (string) $providerValue : '—',
                'mapped' => $mapped,
                'total' => count($allowed),
                'covered' => $rowCovered,
            ];
        }

        return [
            'stock' => $stock,
            'stock_label' => is_scalar($stocks[$stock] ?? null) ? (string) $stocks[$stock] : 'Stock indisponible',
            'stock_valid' => $stockValid,
            'rows' => $rows,
            'covered' => $covered,
        ];
    }
}
