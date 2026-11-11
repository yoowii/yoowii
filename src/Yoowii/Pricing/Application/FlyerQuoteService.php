<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Flyer\FlyerCalculator;
use App\Yoowii\Pricing\Domain\Flyer\FlyerConfiguration;
use App\Yoowii\Pricing\Domain\Flyer\FlyerPricingPolicy;
use App\Yoowii\Pricing\Domain\Flyer\FlyerQuote;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;

final readonly class FlyerQuoteService
{
    public function __construct(
        private SupplierRouteRepository $routeRepository,
        private SupplierPricingMatrixVersionRepository $matrixRepository,
        private FlyerCalculator $calculator,
    ) {
    }

    public function quote(
        string $productCode,
        FlyerConfiguration $configuration,
        FlyerPricingPolicy $pricingPolicy,
        string $currencyCode,
        \DateTimeImmutable $calculatedAt,
    ): FlyerQuote {
        $routes = $this->routeRepository->findCandidates($productCode, $calculatedAt);
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
            $productCode,
            $configuration,
            $pricingPolicy,
            $currencyCode,
            $calculatedAt,
            $routes,
            $matrices,
        );
    }
}
