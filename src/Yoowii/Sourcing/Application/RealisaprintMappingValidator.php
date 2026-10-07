<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Application\RealisaprintLiveQuoteCalculator;
use App\Yoowii\Pricing\Application\RealisaprintAvailabilityFallback;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintMappingValidator
{
    public function __construct(
        private RealisaprintLiveQuoteCalculator $quotes,
        private RealisaprintAvailabilityFallback $availabilityFallback,
        private RetailPrintPricingPolicyProvider $pricingPolicy,
        private RealisaprintMappingCompleteness $completeness,
        private \App\Yoowii\Pricing\Application\RealisaprintConfiguratorRefresh $configuratorRefresh,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(SupplierRoute $route, SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition, array $sample, \DateTimeImmutable $at): RealisaprintMappingValidation
    {
        $errors = $this->coverageErrors($mapping, $definition);
        $initialState = null;
        $catalog = $this->entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;

        try {
            if (!$catalog instanceof RealisaprintCatalogProduct || !is_array($catalog->configuration()) || !is_array($provider)) {
                throw new \InvalidArgumentException('Configuration fournisseur synchronisée introuvable.');
            }
            $this->completeness->assertComplete($definition, $catalog->configuration(), $provider);
        } catch (\InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }
        if ([] !== $errors) {
            return new RealisaprintMappingValidation($mapping, false, false, null, null, null, $sample, $this->fingerprint($mapping, $sample), $errors, null, $at);
        }

        try {
            $configuration = $definition->definition()->configure($sample);
            $initialConfiguration = $configuration->toArray();
            // This must precede save_configuration/get_price: the supplier can constrain
            // the exact configuration that is eligible for quotation.
            $diagnostic = $this->configuratorRefresh->previewWithDiagnostic($configuration, $mapping);
            $state = $diagnostic['state'];
            $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
            $initialState = [
                'product' => $provider['product'] ?? null,
                'stock' => $provider['stock'] ?? null,
                'mapping_version' => $mapping->version(),
                'schema_version' => $definition->definition()->schemaVersion(),
                // This is the exact configuration that produced state. Keep it
                // separate from the quote configuration, which may be corrected
                // afterwards by availabilityFallback.
                'initial_fingerprint' => $this->fingerprint($mapping, $initialConfiguration),
                'initial_configuration' => $initialConfiguration,
                'state' => $state,
                'show_variables_request' => $diagnostic['request'],
                'show_variables_response' => $diagnostic['response'],
            ];
            $configuration = $definition->definition()->configure($this->availabilityFallback->apply($configuration->toArray(), $state, $definition));
            $quote = $this->quotes->quoteDraftMapping($route, $configuration, $this->pricingPolicy->get(), 'EUR', $at, $mapping->configurationMapping(), $mapping->version());

            $fingerprint = $this->fingerprint($mapping, $configuration->toArray());
            $initialState['fingerprint'] = $fingerprint;
            $initialState['configuration'] = $configuration->toArray();

            return new RealisaprintMappingValidation($mapping, true, true, $quote->supplierCost(), $quote->productionCost(), $quote->shippingCost(), $configuration->toArray(), $fingerprint, [], null, $at, $initialState);
        } catch (\Throwable $exception) {
            return new RealisaprintMappingValidation($mapping, true, false, null, null, null, $sample, $this->fingerprint($mapping, $sample), [], $this->safeDetail($exception->getMessage()), $at, $initialState);
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

    /** @return array<string, string|int|float> */
    public function sample(PersistedPrintProductDefinition $definition): array
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
            if ('float' === ($option['type'] ?? null)) {
                $sample[$code] = is_numeric($default) && (float) $default > 0 ? (float) $default : (float) ($option['minimum'] ?? 1);
            }
            if ('text' === ($option['type'] ?? null) && is_string($default) && '' !== trim($default)) {
                $sample[$code] = $default;
            }
        }

        return $sample;
    }

    /**  array<string, string|int|float> $sample */
    public function fingerprint(SupplierProductMappingVersion $mapping, array $sample): string
    {
        return hash('sha256', json_encode(['mapping' => $mapping->configurationMapping(), 'sample' => $sample], JSON_THROW_ON_ERROR));
    }

    private function safeDetail(string $message): string
    {
        $message = preg_replace('/(?:api[_-]?key|password|authorization)\s*[:=]\s*\S+/i', '[redacted]', $message) ?? 'Validation Realisaprint impossible.';

        return $message;
    }
}
