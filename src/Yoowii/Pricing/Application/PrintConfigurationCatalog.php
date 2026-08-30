<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;

final readonly class PrintConfigurationCatalog
{
    public function __construct(
        private SupplierRouteRepository $routeRepository,
        private SupplierPricingMatrixVersionRepository $matrixRepository,
    ) {
    }

    /** @return array<string, list<string|int>> */
    public function availableOptions(
        PrintProductDefinition $definition,
        string $currencyCode,
        \DateTimeImmutable $at,
    ): array {
        $routes = $this->routeRepository->findCandidates($definition->productCode(), $at);
        $supplierProducts = array_map(
            static fn (SupplierRoute $route) => $route->supplierProduct(),
            $routes,
        );
        $matrices = $this->matrixRepository->findSelectableFor($supplierProducts, $currencyCode, $at);
        /** @var array<string, array<string, string|int>> $collectedOptions */
        $collectedOptions = [];

        foreach ($definition->pricingAxes() as $axis) {
            $collectedOptions[$axis] = [];
        }

        foreach ($matrices as $matrix) {
            $payload = $matrix->matrix();

            if (
                'print.matrix_exact' !== ($payload['calculator'] ?? null) ||
                $definition->productCode() !== ($payload['product_code'] ?? null) ||
                $definition->schemaVersion() !== ($payload['product_schema_version'] ?? null)
            ) {
                continue;
            }

            $entries = $payload['entries'] ?? null;

            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                $configuration = is_array($entry) ? ($entry['configuration'] ?? null) : null;

                if (!is_array($configuration)) {
                    continue;
                }

                foreach ($definition->pricingAxes() as $axis) {
                    $value = $configuration[$axis] ?? null;

                    if (is_string($value) || is_int($value)) {
                        $collectedOptions[$axis][$this->valueKey($value)] = $value;
                    }
                }
            }
        }

        $options = [];

        foreach ($collectedOptions as $axis => $values) {
            $sortedValues = array_values($values);
            usort(
                $sortedValues,
                static fn (string|int $left, string|int $right): int => strnatcasecmp((string) $left, (string) $right),
            );
            $options[$axis] = $sortedValues;
        }

        return $options;
    }

    private function valueKey(string|int $value): string
    {
        return sprintf('%s:%s', get_debug_type($value), (string) $value);
    }
}
