<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Flyer;

use App\Yoowii\Pricing\Domain\Flyer\Exception\FlyerPriceNotFound;
use App\Yoowii\Pricing\Domain\PricingSnapshot;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

final readonly class FlyerCalculator
{
    public function __construct(private FixedSupplierRouter $supplierRouter)
    {
    }

    /**
     * @param iterable<SupplierRoute> $routes
     * @param iterable<SupplierPricingMatrixVersion> $matrices
     */
    public function calculate(
        string $productCode,
        FlyerConfiguration $configuration,
        FlyerPricingPolicy $pricingPolicy,
        string $currencyCode,
        \DateTimeImmutable $calculatedAt,
        iterable $routes,
        iterable $matrices,
    ): FlyerQuote {
        $rankedRoutes = $this->supplierRouter->rank($productCode, $calculatedAt, $routes);
        $matrixList = [];

        foreach ($matrices as $matrix) {
            $matrixList[] = $matrix;
        }

        foreach ($rankedRoutes as $route) {
            $matrix = $this->selectMatrix($route, $currencyCode, $calculatedAt, $matrixList);

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
                'print.flyer',
                sprintf('%s@%s', $pricingPolicy->version(), $matrix->version()),
                $configuration->toArray(),
                ['total' => $unitPrice],
                $unitPrice,
                $currencyCode,
                $calculatedAt,
            );

            return new FlyerQuote(
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

        throw new FlyerPriceNotFound($productCode, $configuration);
    }

    /**
     * @param list<SupplierPricingMatrixVersion> $matrices
     */
    private function selectMatrix(
        SupplierRoute $route,
        string $currencyCode,
        \DateTimeImmutable $at,
        array $matrices,
    ): ?SupplierPricingMatrixVersion {
        $eligibleMatrices = array_values(array_filter(
            $matrices,
            static fn (SupplierPricingMatrixVersion $matrix): bool => $matrix->supplierProduct() === $route->supplierProduct()
                && $matrix->currencyCode() === $currencyCode
                && $matrix->isSelectableAt($at),
        ));

        usort(
            $eligibleMatrices,
            static fn (SupplierPricingMatrixVersion $left, SupplierPricingMatrixVersion $right): int => $right->effectiveFrom() <=> $left->effectiveFrom(),
        );

        return $eligibleMatrices[0] ?? null;
    }

    /**
     * @return array{production_cost: int, shipping_cost: int}|null
     */
    private function findCost(
        SupplierPricingMatrixVersion $matrix,
        FlyerConfiguration $configuration,
    ): ?array {
        $payload = $matrix->matrix();

        if (1 !== ($payload['schema_version'] ?? null) || 'print.flyer' !== ($payload['calculator'] ?? null)) {
            throw new \DomainException(sprintf('Pricing matrix "%s" is not a supported flyer matrix.', $matrix->version()));
        }

        $entries = $payload['entries'] ?? null;
        $entry = is_array($entries) ? ($entries[$configuration->matrixKey()] ?? null) : null;

        if (!is_array($entry)) {
            return null;
        }

        $productionCost = $entry['production_cost'] ?? null;
        $shippingCost = $entry['shipping_cost'] ?? null;

        if (!is_int($productionCost) || !is_int($shippingCost) || $productionCost < 0 || $shippingCost < 0) {
            throw new \DomainException(sprintf('Pricing matrix "%s" contains a malformed flyer price.', $matrix->version()));
        }

        return [
            'production_cost' => $productionCost,
            'shipping_cost' => $shippingCost,
        ];
    }
}
