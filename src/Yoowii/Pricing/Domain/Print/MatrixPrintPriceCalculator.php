<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print;

use App\Yoowii\Pricing\Domain\PricingSnapshot;
use App\Yoowii\Pricing\Domain\Print\Exception\PrintPriceNotFound;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

final readonly class MatrixPrintPriceCalculator
{
    public function __construct(private FixedSupplierRouter $supplierRouter)
    {
    }

    /**
     * @param iterable<SupplierRoute> $routes
     * @param iterable<SupplierPricingMatrixVersion> $matrices
     */
    public function calculate(
        PrintConfiguration $configuration,
        PrintPricingPolicy $pricingPolicy,
        string $currencyCode,
        \DateTimeImmutable $calculatedAt,
        iterable $routes,
        iterable $matrices,
    ): PrintQuote {
        $rankedRoutes = $this->supplierRouter->rank(
            $configuration->productCode(),
            $calculatedAt,
            $routes,
        );
        $matrixList = [];

        foreach ($matrices as $matrix) {
            $matrixList[] = $matrix;
        }

        foreach ($rankedRoutes as $route) {
            $matrix = $this->selectMatrix($route, $configuration, $currencyCode, $calculatedAt, $matrixList);

            if (null === $matrix) {
                continue;
            }

            $cost = $this->findCost($matrix, $configuration);

            if (null === $cost) {
                continue;
            }

            $supplierCost = $cost['production_cost'] + $cost['shipping_cost'];
            $margin = $pricingPolicy->calculateMargin($supplierCost);
            $unitPrice = $supplierCost + $margin + $pricingPolicy->handlingFee();
            $pricingSnapshot = new PricingSnapshot(
                'print.matrix_exact',
                sprintf('%s@%s', $pricingPolicy->version(), $matrix->version()),
                array_merge($configuration->snapshotData(), [
                    'sourcing' => [
                        'supplier_code' => $route->supplierProduct()->supplier()->code(),
                        'supplier_product_code' => $route->supplierProduct()->code(),
                        'matrix_version' => $matrix->version(),
                        'matrix_checksum' => $matrix->checksum(),
                        'production_cost' => $cost['production_cost'],
                        'shipping_cost' => $cost['shipping_cost'],
                    ],
                ]),
                ['total' => $unitPrice],
                $unitPrice,
                $currencyCode,
                $calculatedAt,
            );

            return new PrintQuote(
                $pricingSnapshot,
                $route->supplierProduct()->supplier()->code(),
                $route->supplierProduct()->code(),
                $matrix->version(),
                $matrix->checksum(),
                $cost['production_cost'],
                $cost['shipping_cost'],
                $margin,
                $pricingPolicy->handlingFee(),
            );
        }

        throw new PrintPriceNotFound($configuration);
    }

    /** @param list<SupplierPricingMatrixVersion> $matrices */
    private function selectMatrix(
        SupplierRoute $route,
        PrintConfiguration $configuration,
        string $currencyCode,
        \DateTimeImmutable $at,
        array $matrices,
    ): ?SupplierPricingMatrixVersion {
        $eligibleMatrices = array_values(array_filter(
            $matrices,
            fn (SupplierPricingMatrixVersion $matrix): bool => $matrix->supplierProduct() === $route->supplierProduct() &&
                $matrix->currencyCode() === $currencyCode &&
                $matrix->isSelectableAt($at) &&
                $this->isCompatibleMatrix($matrix, $configuration),
        ));

        usort(
            $eligibleMatrices,
            static fn (SupplierPricingMatrixVersion $left, SupplierPricingMatrixVersion $right): int => $right->effectiveFrom() <=> $left->effectiveFrom(),
        );

        return $eligibleMatrices[0] ?? null;
    }

    /** @return array{production_cost: int, shipping_cost: int}|null */
    private function findCost(
        SupplierPricingMatrixVersion $matrix,
        PrintConfiguration $configuration,
    ): ?array {
        $payload = $matrix->matrix();
        $calculator = $payload['calculator'] ?? null;
        $isGenericMatrix = 'print.matrix_exact' === $calculator &&
            $configuration->productCode() === ($payload['product_code'] ?? null) &&
            $configuration->schemaVersion() === ($payload['product_schema_version'] ?? null);
        $isLegacyFlyerMatrix = 'PRINT_FLYER' === $configuration->productCode() &&
            'print.flyer' === $calculator;

        if (1 !== ($payload['schema_version'] ?? null) || (!$isGenericMatrix && !$isLegacyFlyerMatrix)) {
            throw new \DomainException(sprintf(
                'Pricing matrix "%s" is not compatible with product "%s".',
                $matrix->version(),
                $configuration->productCode(),
            ));
        }

        $entries = $payload['entries'] ?? null;
        $entry = is_array($entries) ? ($entries[$configuration->matrixKey()] ?? null) : null;

        if (!is_array($entry)) {
            return null;
        }

        $productionCost = $entry['production_cost'] ?? null;
        $shippingCost = $entry['shipping_cost'] ?? null;

        if (!is_int($productionCost) || !is_int($shippingCost) || $productionCost < 0 || $shippingCost < 0) {
            throw new \DomainException(sprintf('Pricing matrix "%s" contains a malformed print price.', $matrix->version()));
        }

        return [
            'production_cost' => $productionCost,
            'shipping_cost' => $shippingCost,
        ];
    }

    private function isCompatibleMatrix(
        SupplierPricingMatrixVersion $matrix,
        PrintConfiguration $configuration,
    ): bool {
        $payload = $matrix->matrix();

        return (
            'print.matrix_exact' === ($payload['calculator'] ?? null) &&
            $configuration->productCode() === ($payload['product_code'] ?? null) &&
            $configuration->schemaVersion() === ($payload['product_schema_version'] ?? null)
        ) || (
            'PRINT_FLYER' === $configuration->productCode() &&
            'print.flyer' === ($payload['calculator'] ?? null)
        );
    }
}
