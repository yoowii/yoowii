<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\MatrixPrintPriceCalculator;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Print\PrintQuote;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;

final readonly class PrintQuoteService
{
    public function __construct(
        private SupplierRouteRepository $routeRepository,
        private SupplierPricingMatrixVersionRepository $matrixRepository,
        private MatrixPrintPriceCalculator $calculator,
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
