<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Normalizes show_variables without exposing supplier variable IDs to the browser. */
final readonly class RealisaprintConfiguratorRefresh
{
    public function __construct(
        private SupplierRouteRepository $routes,
        private EntityManagerInterface $entityManager,
        private RealisaprintConfigurationMapper $mapper,
        private RealisaprintClient $client,
        private RealisaprintVariableStateCache $variableStateCache,
    ) {
    }

    /** @return array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>} */
    public function refresh(PrintConfiguration $configuration, \DateTimeImmutable $at): array
    {
        foreach ($this->routes->findCandidates($configuration->productCode(), $at) as $route) {
            if ('realisaprint' !== $route->supplierProduct()->supplier()->code()) {
                continue;
            }
            $mapping = $this->mapping($configuration->productCode(), $route, $at);
            if (!$mapping instanceof SupplierProductMappingVersion) {
                continue;
            }
            $mapped = $this->mapper->map($configuration, $route->supplierProduct(), $at);
            return $this->variableStateCache->get($configuration, $mapped, function () use ($mapped, $mapping): array {
                $response = $this->client->post('show_variables', [
                    'product' => $mapped['product'],
                    'stock' => $mapped['stock'],
                    'variables' => $mapped['variables'],
                    'retry' => 1,
                ]);

                return $this->normalizeResponse($response, $mapping);
            });
        }

        throw new \DomainException('Aucune route Realisaprint compatible ne permet de rafraîchir cette configuration.');
    }

    /** @return array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>} */
    public function preview(PrintConfiguration $configuration, SupplierProductMappingVersion $mapping): array
    {
        $mapped = $this->mapper->mapMapping($configuration, $mapping->configurationMapping(), $mapping->version());
        $response = $this->client->post('show_variables', [
            'product' => $mapped['product'],
            'stock' => $mapped['stock'],
            'variables' => $mapped['variables'],
            'retry' => 1,
        ]);

        return $this->normalizeResponse($response, $mapping);
    }

    /**
     * Used exclusively by the back-office publication check. The supplier response
     * remains server-side, in the validation record, and is never returned by the
     * storefront configurator endpoint.
     *
     * @return array{state: array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>}, request: array{product: string|int|float|bool|null, stock: string|int|float|bool|null, variables: array<string, mixed>, retry: int}, response: array<string, mixed>}
     */
    public function previewWithDiagnostic(PrintConfiguration $configuration, SupplierProductMappingVersion $mapping, bool $warm = false): array
    {
        $mapped = $this->mapper->mapMapping($configuration, $mapping->configurationMapping(), $mapping->version());
        $request = [
            'product' => $mapped['product'],
            'stock' => $mapped['stock'],
            'variables' => $mapped['variables'],
            'retry' => 1,
        ];
        $response = $this->client->post('show_variables', $request);
        $state = $this->normalizeResponse($response, $mapping);
        if ($warm) {
            $this->variableStateCache->warm($configuration, $mapped, $state);
        }

        return [
            'state' => $state,
            'request' => $request,
            'response' => $response,
        ];
    }

    private function mapping(string $productCode, SupplierRoute $route, \DateTimeImmutable $at): ?SupplierProductMappingVersion
    {
        $mappings = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $productCode, 'active' => true]);
        foreach ($mappings as $mapping) {
            if ($mapping->supplierProduct() === $route->supplierProduct() && $mapping->isEffectiveAt($at)) {
                return $mapping;
            }
        }

        return null;
    }

    /**
     * `show_variables` returns availability markers (usually 1/0), not values
     * to send back to Realisaprint. Provider values stay in the server mapping;
     * the browser only receives canonical Yoowii codes that remain available.
     *
     * @param array<string, mixed> $response
     *
     * @return array{visibility: array<string, bool>, availability: array<string, list<string>>, current: array<string, string>, alerts: list<string>, infos: list<string>}
     */
    public function normalizeResponse(array $response, SupplierProductMappingVersion $mapping): array
    {
        $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
        $rules = is_array($provider) && is_array($provider['variables'] ?? null) ? $provider['variables'] : [];
        $this->assertUsableShowVariablesResponse($response, $rules);
        $visibility = [];
        $availability = [];
        $current = [];
        foreach ($rules as $providerVariable => $rule) {
            if (!is_string($providerVariable) || !is_array($rule) || !is_string($rule['option'] ?? null)) {
                continue;
            }
            $option = $rule['option'];
            $visibilitySource = $response['visibility'] ?? $response['variable_visibility'] ?? null;
            $visibilityValue = is_array($visibilitySource)
                ? ($visibilitySource[$providerVariable] ?? $visibilitySource[$option] ?? ($response[$providerVariable] ?? true))
                : ($response[$providerVariable] ?? true);
            $visibility[$option] = $this->isAvailable($visibilityValue);
            $reverse = [];
            foreach ((is_array($rule['values'] ?? null) ? $rule['values'] : []) as $canonical => $providerValue) {
                if (is_string($canonical) && is_scalar($providerValue)) {
                    $reverse[(string) $providerValue][] = $canonical;
                }
            }
            // show_variables documents variable_values as a map of available
            // provider values to their labels. The labels are descriptive, not
            // availability markers: every key in this map is selectable.
            $variableValues = $response['variable_values'] ?? null;
            $availabilitySource = is_array($variableValues) ? ($variableValues[$providerVariable] ?? null) : null;
            $providerValuesSource = is_array($availabilitySource);
            // Keep compatibility with historic normalized responses which
            // exposed choices directly under the canonical option code.
            if (!is_array($availabilitySource)) {
                $availabilitySource = $response[$option] ?? null;
            }
            if (is_array($availabilitySource)) {
                $availability[$option] = [];
                foreach ($availabilitySource as $candidate => $_label) {
                    $canonical = $providerValuesSource
                        ? (1 === count($reverse[(string) $candidate] ?? []) ? $reverse[(string) $candidate][0] : null)
                        : (array_key_exists((string) $candidate, is_array($rule['values'] ?? null) ? $rule['values'] : []) ? (string) $candidate : null);
                    if (null !== $canonical) {
                        $availability[$option][] = $canonical;
                    }
                }
                $availability[$option] = array_values(array_unique($availability[$option]));
            }
            // v2.23 uses current_values. current_variables was used by earlier
            // documentation, while variables is retained for legacy responses.
            $providerValues = $response['current_values'] ?? $response['current_variables'] ?? $response['variables'] ?? null;
            $providerCurrent = is_array($providerValues) ? ($providerValues[$providerVariable] ?? null) : null;
            if (is_scalar($providerCurrent)) {
                $providerCurrent = (string) $providerCurrent;
                if (array_key_exists($providerCurrent, is_array($rule['values'] ?? null) ? $rule['values'] : [])) {
                    $current[$option] = $providerCurrent;
                } elseif (1 === count($reverse[$providerCurrent] ?? [])) {
                    $current[$option] = $reverse[$providerCurrent][0];
                }
            }
        }

        return [
            'visibility' => $visibility,
            'availability' => $availability,
            'current' => $current,
            'alerts' => $this->messages($response['alerts'] ?? []),
            'infos' => $this->messages($response['infos'] ?? []),
        ];
    }

    /**
     * The HTTP client adds _http_status to every live provider response. A
     * failed (or structurally empty) show_variables response must never be
     * normalized with the historical "visible by default" compatibility
     * fallback: that would reveal every storefront field and overwrite a
     * previously valid browser state.
     *
     * @param array<string, mixed> $response
     * @param array<string, mixed> $rules
     */
    private function assertUsableShowVariablesResponse(array $response, array $rules): void
    {
        if (!array_key_exists('_http_status', $response)) {
            return;
        }

        $status = $response['_http_status'];
        if (!is_int($status) || $status < 200 || $status >= 300) {
            throw new \DomainException('Realisaprint ne peut pas mettre à jour les options pour le moment.');
        }

        $visibility = $response['visibility'] ?? $response['variable_visibility'] ?? null;
        $values = $response['variable_values'] ?? null;
        foreach ($rules as $providerVariable => $rule) {
            if (!is_string($providerVariable) || !is_array($rule)) {
                continue;
            }
            $option = $rule['option'] ?? null;
            if (
                array_key_exists($providerVariable, $response)
                || (is_string($option) && array_key_exists($option, $response))
                || (is_array($visibility) && (array_key_exists($providerVariable, $visibility) || (is_string($option) && array_key_exists($option, $visibility))))
                || (is_array($values) && array_key_exists($providerVariable, $values))
            ) {
                return;
            }
        }

        throw new \DomainException('Realisaprint a renvoyé un état de configuration incomplet.');
    }

    private function isAvailable(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return 0.0 !== (float) $value;
        }

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'available'], true);
    }

    /** @return list<string> */
    private function messages(mixed $messages): array
    {
        if (!is_array($messages)) {
            return [];
        }

        return array_values(array_filter($messages, 'is_string'));
    }
}
