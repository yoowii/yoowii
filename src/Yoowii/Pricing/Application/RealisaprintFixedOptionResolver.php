<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;
use Doctrine\ORM\EntityManagerInterface;

/** Resolves provider-imposed values to their canonical Yoowii option values. */
final readonly class RealisaprintFixedOptionResolver
{
    public function __construct(private SupplierRouteRepository $routes, private EntityManagerInterface $entityManager)
    {
    }

    /** @return array<string, array{value: string|int|float, label: string}> */
    public function forProduct(string $productCode, \DateTimeImmutable $at): array
    {
        $fixed = [];
        foreach ($this->routes->findCandidates($productCode, $at) as $route) {
            if ('realisaprint' !== $route->supplierProduct()->supplier()->code()) {
                continue;
            }
            foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $productCode, 'active' => true]) as $mapping) {
                if ($mapping->supplierProduct() !== $route->supplierProduct() || !$mapping->isEffectiveAt($at)) {
                    continue;
                }
                foreach ($this->forMapping($mapping) as $option => $candidate) {
                    if (isset($fixed[$option]) && $fixed[$option]['value'] !== $candidate['value']) {
                        throw new \DomainException(sprintf('Les routes Realisaprint actives imposent des valeurs incompatibles pour « %s ».', $option));
                    }
                    $fixed[$option] = $candidate;
                }
            }
        }

        return $fixed;
    }

    /** @return array<string, array{value: string|int|float, label: string}> */
    public function forMapping(?SupplierProductMappingVersion $mapping): array
    {
        if (!$mapping instanceof SupplierProductMappingVersion) {
            return [];
        }

        $fixed = [];
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        $rules = is_array($provider) ? ($provider['variables'] ?? null) : null;
        if (!is_array($rules)) {
            return [];
        }
        foreach ($rules as $rule) {
            if (!is_array($rule) || !is_string($rule['option'] ?? null) || !is_scalar($rule['fixed_value'] ?? null)) {
                continue;
            }
            $providerValues = $rule['values'] ?? null;
            $canonical = $this->canonicalValue($rule['fixed_value'], is_array($providerValues) ? $providerValues : []);
            if (null === $canonical) {
                throw new \DomainException(sprintf('La valeur fixe Realisaprint de « %s » ne correspond pas au mapping publié.', $rule['option']));
            }
            $fixed[$rule['option']] = ['value' => $canonical, 'label' => (string) $rule['fixed_value']];
        }

        return $fixed;
    }

    /** @param array<mixed> $values */
    private function canonicalValue(mixed $fixedValue, array $values): string|int|null
    {
        foreach ($values as $canonical => $providerValue) {
            if (is_scalar($providerValue) && is_scalar($fixedValue) && (string) $providerValue === (string) $fixedValue) {
                return ctype_digit((string) $canonical) ? (int) $canonical : (string) $canonical;
            }
        }

        return null;
    }
}
