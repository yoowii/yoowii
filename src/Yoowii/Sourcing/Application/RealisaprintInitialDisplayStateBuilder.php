<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Application\RealisaprintConfiguratorRefresh;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Psr\Log\LoggerInterface;

/** Builds the cache used to render a Realisaprint configurator without a quote. */
final readonly class RealisaprintInitialDisplayStateBuilder
{
    public function __construct(
        private RealisaprintConfiguratorRefresh $configuratorRefresh,
        private LoggerInterface $logger,
        private int $timeToLive,
    ) {
        if ($timeToLive <= 0) {
            throw new \InvalidArgumentException('The initial Realisaprint display-state TTL must be positive.');
        }
    }

    /** @return array<string, mixed> */
    public function build(SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition, \DateTimeImmutable $at): array
    {
        $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
        $state = [
            'mapping_version' => $mapping->version(),
            'schema_version' => $definition->definition()->schemaVersion(),
            'stock' => is_array($provider) && is_scalar($provider['stock'] ?? null) ? (string) $provider['stock'] : null,
            'generated_at' => $at->format(\DATE_ATOM),
        ];

        try {
            $configuration = $this->displayConfiguration($mapping, $definition);
            $state['display_configuration'] = $configuration;
            $state['display_fingerprint'] = $this->fingerprint($mapping, $configuration);
            $diagnostic = $this->configuratorRefresh->previewWithDiagnostic($definition->definition()->configure($configuration), $mapping);
            $state += $diagnostic['state'];
            $state['show_variables_request'] = $diagnostic['request'];
            $state['show_variables_response'] = $diagnostic['response'];
        } catch (\Throwable $exception) {
            // A failed optimization must leave the published static form usable.
            $detail = $this->safeDetail($exception->getMessage());
            $state['error'] = $detail;
            $this->logger->warning('Realisaprint initial display state could not be refreshed.', [
                'mapping_id' => $mapping->id(),
                'detail' => $detail,
            ]);
        }

        return $state;
    }

    /** @return array<string, string|int|float> */
    public function displayConfiguration(SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition): array
    {
        $values = [];
        foreach ($definition->options() as $code => $option) {
            $default = $option['default'] ?? null;
            if (is_string($default) || is_int($default) || is_float($default)) {
                $values[$code] = $default;

                continue;
            }
            if (in_array($option['type'] ?? null, ['integer', 'float'], true) && isset($option['minimum']) && is_numeric($option['minimum'])) {
                $values[$code] = 'integer' === $option['type'] ? (int) $option['minimum'] : (float) $option['minimum'];
            } elseif ('text' === ($option['type'] ?? null) && true === ($option['required'] ?? true)) {
                // Realisaprint rejects an empty required free field; this value is
                // never taken from the administrative price-test form.
                $values[$code] = '-';
            }
        }
        foreach ($this->fixedValues($mapping) as $code => $value) {
            $values[$code] = $value;
        }

        return $definition->definition()->configure($values)->toArray();
    }

    /** @param array<string, mixed> $state */
    public function isUsable(array $state, SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition, \DateTimeImmutable $now): bool
    {
        $reason = $this->unusableReason($state, $mapping, $definition, $now);
        if (null !== $reason) {
            $this->logger->debug('Realisaprint initial display state was rejected for the storefront.', [
                'mapping_id' => $mapping->id(),
                'reason' => $reason,
            ]);
        }

        return null === $reason;
    }

    /** @param array<string, mixed> $state */
    public function unusableReason(array $state, SupplierProductMappingVersion $mapping, PersistedPrintProductDefinition $definition, \DateTimeImmutable $now): ?string
    {
        $generatedAt = isset($state['generated_at']) && is_string($state['generated_at']) ? \DateTimeImmutable::createFromFormat(\DATE_ATOM, $state['generated_at']) : false;
        $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
        if (!$generatedAt instanceof \DateTimeImmutable) {
            return 'generated_at_invalid';
        }
        if ($generatedAt < $now->modify('-' . $this->timeToLive . ' seconds')) {
            return 'expired';
        }
        if (isset($state['error'])) {
            return 'build_error';
        }
        if (($state['mapping_version'] ?? null) !== $mapping->version()) {
            return 'mapping_version_mismatch';
        }
        if (($state['schema_version'] ?? null) !== $definition->definition()->schemaVersion()) {
            return 'schema_version_mismatch';
        }
        if (($state['stock'] ?? null) !== (is_array($provider) && is_scalar($provider['stock'] ?? null) ? (string) $provider['stock'] : null)) {
            return 'stock_mismatch';
        }
        if (!is_array($state['display_configuration'] ?? null) || !is_string($state['display_fingerprint'] ?? null) ||
            !is_array($state['visibility'] ?? null) || !is_array($state['availability'] ?? null) || !is_array($state['current'] ?? null)) {
            return 'json_shape_invalid';
        }

        try {
            $configuration = $this->displayConfiguration($mapping, $definition);
        } catch (\Throwable) {
            return 'display_configuration_invalid';
        }

        if ($configuration !== $state['display_configuration']) {
            return 'display_configuration_mismatch';
        }

        return hash_equals($state['display_fingerprint'], $this->fingerprint($mapping, $configuration)) ? null : 'fingerprint_mismatch';
    }

    /** @return array<string, string|int|float> */
    private function fixedValues(SupplierProductMappingVersion $mapping): array
    {
        $configurationMapping = $mapping->configurationMapping();
        $provider = $configurationMapping['realisaprint'] ?? null;
        $rules = is_array($provider) ? ($provider['variables'] ?? []) : [];
        $values = [];
        foreach (is_array($rules) ? $rules : [] as $rule) {
            if (!is_array($rule) || !is_string($rule['option'] ?? null) || !is_scalar($rule['fixed_value'] ?? null)) {
                continue;
            }
            foreach (is_array($rule['values'] ?? null) ? $rule['values'] : [] as $canonical => $provider) {
                if (is_scalar($provider) && (string) $provider === (string) $rule['fixed_value']) {
                    $values[$rule['option']] = ctype_digit((string) $canonical) ? (int) $canonical : (string) $canonical;

                    continue 2;
                }
            }

            throw new \DomainException(sprintf('La valeur fixe de « %s » ne correspond pas au mapping publié.', $rule['option']));
        }

        return $values;
    }

    /** @param array<string, string|int|float> $configuration */
    private function fingerprint(SupplierProductMappingVersion $mapping, array $configuration): string
    {
        return hash('sha256', json_encode(['mapping' => $mapping->configurationMapping(), 'display_configuration' => $configuration], \JSON_THROW_ON_ERROR));
    }

    private function safeDetail(string $message): string
    {
        return preg_replace('/(?:api[_-]?key|password|authorization)\s*[:=]\s*\S+/i', '[redacted]', $message) ?? 'Aperçu Realisaprint indisponible.';
    }
}
