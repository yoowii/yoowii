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
    ) {
    }

    /** @return array{visibility: array<string, bool>, values: array<string, array<string, string>>, current: array<string, string|int>, alerts: list<string>, infos: list<string>} */
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
            $response = $this->client->post('show_variables', [
                'product' => $mapped['product'],
                'stock' => $mapped['stock'],
                'variables' => $mapped['variables'],
                'retry' => 1,
            ]);

            return $this->normalize($response, $mapping);
        }

        throw new \DomainException('Aucune route Realisaprint compatible ne permet de rafraîchir cette configuration.');
    }

    /** @return array{visibility: array<string, bool>, values: array<string, array<string, string>>, current: array<string, string|int>, alerts: list<string>, infos: list<string>} */
    public function preview(PrintConfiguration $configuration, SupplierProductMappingVersion $mapping): array
    {
        $mapped = $this->mapper->mapMapping($configuration, $mapping->configurationMapping(), $mapping->version());
        $response = $this->client->post('show_variables', [
            'product' => $mapped['product'],
            'stock' => $mapped['stock'],
            'variables' => $mapped['variables'],
            'retry' => 1,
        ]);

        return $this->normalize($response, $mapping);
    }

    private function mapping(string $productCode, SupplierRoute $route, \DateTimeImmutable $at): ?SupplierProductMappingVersion
    {
        $mappings = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $productCode, 'active' => true]);
        foreach ($mappings as $mapping) {
            if ($mapping instanceof SupplierProductMappingVersion && $mapping->supplierProduct() === $route->supplierProduct() && $mapping->isEffectiveAt($at)) {
                return $mapping;
            }
        }

        return null;
    }

    /** @return array{visibility: array<string, bool>, values: array<string, array<string, string>>, current: array<string, string|int>, alerts: list<string>, infos: list<string>} */
    private function normalize(array $response, SupplierProductMappingVersion $mapping): array
    {
        $provider = $mapping->configurationMapping()['realisaprint'] ?? [];
        $rules = is_array($provider) && is_array($provider['variables'] ?? null) ? $provider['variables'] : [];
        $visibility = [];
        $values = [];
        $current = [];
        foreach ($rules as $providerVariable => $rule) {
            if (!is_string($providerVariable) || !is_array($rule) || !is_string($rule['option'] ?? null)) {
                continue;
            }
            $option = $rule['option'];
            $visibility[$option] = true === ($response[$providerVariable] ?? true);
            $providerValues = is_array($response['variable_values'][$providerVariable] ?? null) ? $response['variable_values'][$providerVariable] : [];
            $reverse = [];
            foreach ((is_array($rule['values'] ?? null) ? $rule['values'] : []) as $canonical => $providerValue) {
                if (is_string($canonical) && is_scalar($providerValue)) {
                    $reverse[(string) $providerValue] = $canonical;
                }
            }
            foreach ($providerValues as $providerValue => $label) {
                if (is_scalar($providerValue) && is_string($label) && isset($reverse[(string) $providerValue])) {
                    $values[$option][$reverse[(string) $providerValue]] = $label;
                }
            }
            $providerCurrent = $response['variables'][$providerVariable] ?? null;
            if (is_scalar($providerCurrent) && isset($reverse[(string) $providerCurrent])) {
                $current[$option] = $reverse[(string) $providerCurrent];
            }
        }

        return [
            'visibility' => $visibility,
            'values' => $values,
            'current' => $current,
            'alerts' => $this->messages($response['alerts'] ?? []),
            'infos' => $this->messages($response['infos'] ?? []),
        ];
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
