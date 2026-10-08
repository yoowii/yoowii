<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Application\RealisaprintLiveQuoteCalculator;
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
        private RetailPrintPricingPolicyProvider $pricingPolicy,
        private RealisaprintMappingCompleteness $completeness,
        private \App\Yoowii\Pricing\Application\RealisaprintConfiguratorRefresh $configuratorRefresh,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function validate(SupplierRoute $route, SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition, array $sample, \DateTimeImmutable $at): RealisaprintMappingValidation
    {
        $errors = $this->coverageErrors($mapping, $definition);
        $priceDiagnostic = null;
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
            // The price-validation path deliberately uses its own complete test
            // configuration. It must not seed the storefront display cache.
            $diagnostic = $this->configuratorRefresh->previewWithDiagnostic($configuration, $mapping);
            $priceDiagnostic = ['calls' => [['operation' => 'show_variables', 'request' => $diagnostic['request'], 'response' => $diagnostic['response']]]];
            $this->assertTestValuesAvailable($configuration->toArray(), $diagnostic['state']);
            $quote = $this->quotes->quoteDraftMapping($route, $configuration, $this->pricingPolicy->get(), 'EUR', $at, $mapping->configurationMapping(), $mapping->version(), static function (string $operation, array $request, ?array $response) use (&$priceDiagnostic): void {
                $priceDiagnostic['calls'][] = ['operation' => $operation, 'request' => $request, 'response' => $response];
            });

            $fingerprint = $this->fingerprint($mapping, $configuration->toArray());

            return new RealisaprintMappingValidation($mapping, true, true, $quote->supplierCost(), $quote->productionCost(), $quote->shippingCost(), $configuration->toArray(), $fingerprint, [], null, $at, $priceDiagnostic);
        } catch (\Throwable $exception) {
            return new RealisaprintMappingValidation($mapping, true, false, null, null, null, $sample, $this->fingerprint($mapping, $sample), [], $this->safeDetail($exception->getMessage()), $at, $priceDiagnostic);
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
        return hash('sha256', json_encode(['mapping' => $mapping->configurationMapping(), 'sample' => $sample], \JSON_THROW_ON_ERROR));
    }

    /** @param array<string, string|int|float> $configuration @param array<string, mixed> $state */
    private function assertTestValuesAvailable(array $configuration, array $state): void
    {
        foreach (is_array($state['availability'] ?? null) ? $state['availability'] : [] as $option => $available) {
            if (!is_string($option) || !is_array($available) || !array_key_exists($option, $configuration)) {
                continue;
            }
            if (!in_array((string) $configuration[$option], array_map('strval', $available), true)) {
                throw new \DomainException(sprintf('La valeur d’essai de « %s » n’est plus disponible chez Realisaprint.', $option));
            }
        }
    }

    private function safeDetail(string $message): string
    {
        $message = preg_replace('/(?:api[_-]?key|password|authorization)\s*[:=]\s*\S+/i', '[redacted]', $message) ?? 'Validation Realisaprint impossible.';

        return $message;
    }
}
