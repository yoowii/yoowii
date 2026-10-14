<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\PricingMatrixStatus;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;

/** Builds a read-only operational view; it deliberately does not change sourcing state. */
final class SourcingProductOverviewBuilder
{
    /**
     * @param iterable<SupplierRoute> $routes
     * @param iterable<SupplierProductMappingVersion> $mappings
     * @param iterable<SupplierPricingMatrixVersion> $matrices
     *
     * @return array<string, array<string, mixed>> keyed by Yoowii product code
     */
    public function build(iterable $routes, iterable $mappings, iterable $matrices, \DateTimeImmutable $now): array
    {
        $items = [];
        foreach ($routes as $route) {
            $items[$route->yoowiiProductCode()]['routes'][] = $route;
        }
        foreach ($mappings as $mapping) {
            $items[$mapping->yoowiiProductCode()]['mappings'][] = $mapping;
        }
        foreach ($matrices as $matrix) {
            $matrixProductCode = $matrix->matrix()['product_code'] ?? null;
            if (is_string($matrixProductCode) && '' !== $matrixProductCode) {
                $items[$matrixProductCode]['matrices'][] = $matrix;
            }
            foreach ($items as $code => $item) {
                foreach ($item['routes'] ?? [] as $route) {
                    if ($route->supplierProduct() === $matrix->supplierProduct()) {
                        $items[$code]['matrices'][] = $matrix;
                    }
                }
            }
        }

        foreach ($items as $code => $item) {
            /** @var list<SupplierRoute> $productRoutes */
            $productRoutes = $item['routes'] ?? [];
            /** @var list<SupplierProductMappingVersion> $productMappings */
            $productMappings = $item['mappings'] ?? [];
            /** @var list<SupplierPricingMatrixVersion> $productMatrices */
            $productMatrices = array_values(array_unique($item['matrices'] ?? [], \SORT_REGULAR));
            $items[$code] = $this->summarize($productRoutes, $productMappings, $productMatrices, $now);
        }

        ksort($items);

        return $items;
    }

    /**
     * @param list<SupplierRoute> $routes
     * @param list<SupplierProductMappingVersion> $mappings
     * @param list<SupplierPricingMatrixVersion> $matrices
     *
     * @return array<string, mixed>
     */
    private function summarize(array $routes, array $mappings, array $matrices, \DateTimeImmutable $now): array
    {
        usort($routes, static fn (SupplierRoute $left, SupplierRoute $right): int => $left->priority() <=> $right->priority());
        $eligible = array_values(array_filter($routes, static fn (SupplierRoute $route): bool => $route->isEligibleFor($route->yoowiiProductCode(), $now)));
        $currentMappings = array_values(array_filter($mappings, static fn (SupplierProductMappingVersion $mapping): bool => $mapping->isEffectiveAt($now)));
        $currentMatrices = array_values(array_filter($matrices, static fn (SupplierPricingMatrixVersion $matrix): bool => $matrix->isSelectableAt($now)));
        $future = [
            'routes' => array_values(array_filter($routes, static fn (SupplierRoute $route): bool => $route->effectiveFrom() > $now)),
            'mappings' => array_values(array_filter($mappings, static fn (SupplierProductMappingVersion $mapping): bool => $mapping->effectiveFrom() > $now)),
            'matrices' => array_values(array_filter($matrices, static fn (SupplierPricingMatrixVersion $matrix): bool => $matrix->effectiveFrom() > $now)),
        ];
        $errors = [];
        $recommendations = [];
        $primary = $eligible[0] ?? null;

        if (!$primary instanceof SupplierRoute) {
            $errors[] = 'Aucune route éligible maintenant.';
        } else {
            $supplier = $primary->supplierProduct()->supplier();
            if (!$supplier->isActive()) {
                $errors[] = 'Le fournisseur principal est inactif.';
            }
            if (!$primary->supplierProduct()->isActive()) {
                $errors[] = 'La référence fournisseur principale est inactive.';
            }
            if (in_array($supplier->integrationMode(), [SupplierIntegrationMode::Api, SupplierIntegrationMode::Hybrid], true)) {
                $hasMapping = false;
                foreach ($currentMappings as $mapping) {
                    if ($mapping->supplierProduct() === $primary->supplierProduct()) {
                        $hasMapping = true;
                        break;
                    }
                }
                if (!$hasMapping) {
                    $errors[] = 'Le fournisseur API principal n’a pas de mapping applicable.';
                }
            }
        }

        $activePriorities = [];
        foreach ($routes as $route) {
            if ($route->isActive() && $route->effectiveFrom() <= $now && (null === $route->effectiveUntil() || $route->effectiveUntil() > $now)) {
                $activePriorities[$route->priority()][] = $route;
            }
        }
        foreach ($activePriorities as $samePriorityRoutes) {
            if (count($samePriorityRoutes) > 1) {
                $errors[] = 'Deux routes actives partagent la même priorité.';
                break;
            }
        }
        if (null !== $primary && count($eligible) < 2) {
            $recommendations[] = 'Aucune route de secours éligible.';
        }
        if ([] !== $future['routes'] || [] !== $future['mappings'] || [] !== $future['matrices']) {
            $recommendations[] = 'Des changements sont planifiés.';
        }
        foreach ($mappings as $mapping) {
            if ($mapping->isActive() && null === $mapping->publishedAt()) {
                $recommendations[] = 'Un mapping brouillon attend une publication.';
                break;
            }
        }

        return [
            'routes' => $routes,
            'mappings' => $mappings,
            'matrices' => $matrices,
            'primary_route' => $primary,
            'current_mappings' => $currentMappings,
            'current_matrices' => $currentMatrices,
            'future' => $future,
            'errors' => array_values(array_unique($errors)),
            'recommendations' => array_values(array_unique($recommendations)),
        ];
    }
}
