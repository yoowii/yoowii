<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;

/** Ensures a draft mapping can be used as-is by the Realisaprint API. */
final class RealisaprintMappingCompleteness
{
    /** @param array<string, mixed> $configuration
     * @param array<string, mixed> $mapping */
    public function assertComplete(PersistedPrintProductDefinition $definition, array $configuration, array $mapping): void
    {
        $stocks = $configuration['stocks'] ?? null;
        $providerVariables = $configuration['variables'] ?? null;
        if (!is_array($stocks) || !is_array($providerVariables)) {
            throw new \InvalidArgumentException('La configuration Realisaprint synchronisée est obligatoire pour enregistrer le mapping.');
        }

        $stock = $mapping['stock'] ?? null;
        $rules = $mapping['variables'] ?? null;
        if (!is_scalar($stock) || '' === trim((string) $stock) || !array_key_exists((string) $stock, $stocks)) {
            throw new \InvalidArgumentException('Le stock Realisaprint doit être sélectionné dans la configuration synchronisée.');
        }
        if (!is_array($rules)) {
            throw new \InvalidArgumentException('Toutes les variables Realisaprint synchronisées doivent être mappées.');
        }

        $expectedIds = [];
        foreach ($providerVariables as $id => $variable) {
            if (is_string($id) && is_array($variable)) {
                $expectedIds[$id] = $variable;
            }
        }
        if ([] === $expectedIds) {
            throw new \InvalidArgumentException('La configuration Realisaprint ne contient aucune variable exploitable.');
        }
        if (array_diff(array_keys($expectedIds), array_keys($rules)) !== [] || array_diff(array_keys($rules), array_keys($expectedIds)) !== []) {
            throw new \InvalidArgumentException('Le mapping doit contenir exactement toutes les variables de la configuration Realisaprint synchronisée.');
        }

        $options = $definition->options();
        $mappedOptions = [];
        foreach ($expectedIds as $id => $providerVariable) {
            $rule = $rules[$id] ?? null;
            if (!is_array($rule) || !is_string($rule['option'] ?? null) || !array_key_exists($rule['option'], $options)) {
                throw new \InvalidArgumentException(sprintf('La variable Realisaprint « %s » doit viser une option Yoowii existante.', $id));
            }
            $optionCode = $rule['option'];
            if (isset($mappedOptions[$optionCode])) {
                throw new \InvalidArgumentException(sprintf('L’option Yoowii « %s » ne peut pas être mappée deux fois.', $optionCode));
            }
            $mappedOptions[$optionCode] = true;
            if (($options[$optionCode]['provider_variable'] ?? null) !== $id) {
                throw new \InvalidArgumentException(sprintf('La variable Realisaprint « %s » ne correspond pas à l’option Yoowii sélectionnée.', $id));
            }

            $fixed = $options[$optionCode]['fixed_value'] ?? null;
            if (is_string($fixed)) {
                $providerFixed = $rule['fixed_value'] ?? null;
                $expected = $options[$optionCode]['provider_fixed_value'] ?? null;
                if (!is_string($providerFixed) || !is_string($expected) || $providerFixed !== $expected) {
                    throw new \InvalidArgumentException(sprintf('La valeur fixe de « %s » doit être « %s ».', $id, (string) $expected));
                }
                if (true !== ($providerVariable['readonly'] ?? false) || false !== ($providerVariable['values'] ?? null) || $expected !== ($providerVariable['default'] ?? null)) {
                    throw new \InvalidArgumentException(sprintf('La valeur fixe de « %s » a changé dans la configuration Realisaprint synchronisée.', $id));
                }

                continue;
            }
            $values = $rule['values'] ?? null;
            if (!is_array($values) || ([] !== $values && array_is_list($values))) {
                throw new \InvalidArgumentException(sprintf('Les valeurs de « %s » doivent être un objet JSON.', $id));
            }
            $sourceValues = $providerVariable['values'] ?? false;
            if (false === $sourceValues || null === $sourceValues) {
                if ([] !== $values) {
                    throw new \InvalidArgumentException(sprintf('« %s » est une valeur numérique libre : ses correspondances doivent être {}.', $id));
                }
                continue;
            }
            if (!is_array($sourceValues)) {
                throw new \InvalidArgumentException(sprintf('Les valeurs synchronisées de « %s » sont invalides.', $id));
            }
            $allowed = $options[$optionCode]['allowed_values'] ?? [];
            if (!is_array($allowed)) {
                throw new \InvalidArgumentException(sprintf('Les valeurs Yoowii de « %s » sont invalides.', $optionCode));
            }
            foreach ($allowed as $canonical) {
                if (!array_key_exists((string) $canonical, $values)) {
                    throw new \InvalidArgumentException(sprintf('La valeur Yoowii « %s » de « %s » n’est pas mappée.', (string) $canonical, $optionCode));
                }
                $providerValue = $values[(string) $canonical];
                if (!is_scalar($providerValue) || !array_key_exists((string) $providerValue, $sourceValues)) {
                    throw new \InvalidArgumentException(sprintf('La valeur fournisseur de « %s » doit provenir de la configuration synchronisée.', $id));
                }
            }
            if (count($values) !== count($allowed)) {
                throw new \InvalidArgumentException(sprintf('Les correspondances de « %s » doivent couvrir exactement les valeurs Yoowii.', $id));
            }
        }
    }
}
