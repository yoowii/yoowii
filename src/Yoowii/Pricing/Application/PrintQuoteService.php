<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\MatrixPrintPriceCalculator;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Print\PrintQuote;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;

final readonly class PrintQuoteService
{
    public function __construct(
        private SupplierRouteRepository $routeRepository,
        private SupplierPricingMatrixVersionRepository $matrixRepository,
        private MatrixPrintPriceCalculator $calculator,
        private FixedSupplierRouter $supplierRouter,
        private RealisaprintLiveQuoteCalculator $realisaprintCalculator,
    ) {
    }

    public function quote(
        PrintConfiguration $configuration,
        PrintPricingPolicy $pricingPolicy,
        string $currencyCode,
        \DateTimeImmutable $calculatedAt,
    ): PrintQuote {
        $routes = $this->routeRepository->findCandidates(
            $configuration->productCode(),
            $calculatedAt,
        );
        $supplierProducts = array_map(
            static fn (SupplierRoute $route) => $route->supplierProduct(),
            $routes,
        );
        $matrices = $this->matrixRepository->findSelectableFor(
            $supplierProducts,
            $currencyCode,
            $calculatedAt,
        );

        foreach ($this->supplierRouter->rank($configuration->productCode(), $calculatedAt, $routes) as $route) {
            if (!$this->realisaprintCalculator->supports($route)) {
                continue;
            }
            try {
                return $this->realisaprintCalculator->quote($route, $configuration, $pricingPolicy, $currencyCode, $calculatedAt);
            } catch (\Throwable) {
                // A versioned matrix or a lower-priority route remains the safe fallback.
            }
        }

        return $this->calculator->calculate(
            $configuration,
            $pricingPolicy,
            $currencyCode,
            $calculatedAt,
            $routes,
            $matrices,
        );
    }
}
