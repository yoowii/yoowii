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
                if (!$mapping instanceof SupplierProductMappingVersion || $mapping->supplierProduct() !== $route->supplierProduct() || !$mapping->isEffectiveAt($at)) {
                    continue;
                }
                $rules = $mapping->configurationMapping()['realisaprint']['variables'] ?? [];
                if (!is_array($rules)) {
                    continue;
                }
                foreach ($rules as $rule) {
                    if (!is_array($rule) || !is_string($rule['option'] ?? null) || !is_scalar($rule['fixed_value'] ?? null)) {
                        continue;
                    }
                    $canonical = $this->canonicalValue($rule['fixed_value'], $rule['values'] ?? []);
                    if (null === $canonical) {
                        throw new \DomainException(sprintf('La valeur fixe Realisaprint de « %s » ne correspond pas au mapping publié.', $rule['option']));
                    }
                    $candidate = ['value' => $canonical, 'label' => (string) $rule['fixed_value']];
                    if (isset($fixed[$rule['option']]) && $fixed[$rule['option']]['value'] !== $candidate['value']) {
                        throw new \DomainException(sprintf('Les routes Realisaprint actives imposent des valeurs incompatibles pour « %s ».', $rule['option']));
                    }
                    $fixed[$rule['option']] = $candidate;
                }
            }
        }

        return $fixed;
    }

    /** @param array<mixed> $values */
    private function canonicalValue(mixed $fixedValue, array $values): string|int|float|null
    {
        foreach ($values as $canonical => $providerValue) {
            if ((string) $providerValue === (string) $fixedValue) {
                return ctype_digit((string) $canonical) ? (int) $canonical : (string) $canonical;
            }
        }

        return null;
    }
}
