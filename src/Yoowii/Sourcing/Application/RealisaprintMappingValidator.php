<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Application\RealisaprintLiveQuoteCalculator;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

final readonly class RealisaprintMappingValidator
{
    public function __construct(private RealisaprintLiveQuoteCalculator $quotes, private RetailPrintPricingPolicyProvider $pricingPolicy)
    {
    }

    public function validate(SupplierRoute $route, SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition, \DateTimeImmutable $at): RealisaprintMappingValidation
    {
        $errors = $this->coverageErrors($mapping, $definition);
        if ([] !== $errors) {
            return new RealisaprintMappingValidation($mapping, false, false, null, $errors, null, $at);
        }
        try {
            $configuration = $definition->definition()->configure($this->sample($definition));
            $quote = $this->quotes->quoteDraftMapping($route, $configuration, $this->pricingPolicy->get(), 'EUR', $at, $mapping->configurationMapping(), $mapping->version());

            return new RealisaprintMappingValidation($mapping, true, true, $quote->supplierCost(), [], null, $at);
        } catch (\Throwable $exception) {
            return new RealisaprintMappingValidation($mapping, true, false, null, [], $this->safeDetail($exception->getMessage()), $at);
        }
    }

    /** @return list<string> */
    private function coverageErrors(SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition): array
    {
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider) || !is_array($provider['variables'] ?? null) || !is_scalar($provider['stock'] ?? null)) {
            return ['Le mapping Realisaprint doit contenir un stock et des variables.'];
        }
        $rules = $provider['variables'];
        $mappedOptions = [];
        foreach ($rules as $rule) {
            if (is_array($rule) && is_string($rule['option'] ?? null)) {
                $mappedOptions[$rule['option']] = $rule;
            }
        }
        $errors = [];
        foreach ($definition->pricingAxes() as $option) {
            if (!isset($mappedOptions[$option])) {
                $errors[] = sprintf('L’axe obligatoire « %s » n’est pas mappé.', $option);
                continue;
            }
            $allowed = $definition->options()[$option]['allowed_values'] ?? [];
            $values = $mappedOptions[$option]['values'] ?? null;
            if (!is_array($values)) {
                $errors[] = sprintf('Les valeurs de l’axe « %s » ne sont pas mappées.', $option);
                continue;
            }
            foreach ($allowed as $value) {
                if (!array_key_exists((string) $value, $values)) {
                    $errors[] = sprintf('La valeur « %s » de « %s » n’est pas couverte.', (string) $value, $option);
                }
            }
        }

        return $errors;
    }

    /** @return array<string, string|int> */
    private function sample(PersistedPrintProductDefinition $definition): array
    {
        $sample = [];
        foreach ($definition->options() as $code => $option) {
            $allowed = $option['allowed_values'] ?? [];
            $default = $option['default'] ?? null;
            if ([] !== $allowed) {
                $sample[$code] = (is_string($default) || is_int($default)) && in_array($default, $allowed, true) ? $default : $allowed[0];
                continue;
            }
            if ('integer' === ($option['type'] ?? null)) {
                $sample[$code] = is_int($default) ? $default : (int) ($option['minimum'] ?? 1);
            }
        }

        return $sample;
    }

    private function safeDetail(string $message): string
    {
        $message = preg_replace('/(?:api[_-]?key|password|authorization)\s*[:=]\s*\S+/i', '[redacted]', $message) ?? 'Validation Realisaprint impossible.';

        return mb_substr($message, 0, 280);
    }
}
