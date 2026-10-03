<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\MatrixPrintPriceCalculator;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Print\PrintQuote;
use App\Yoowii\Pricing\Domain\Quote\QuoteFallbackReason;
use App\Yoowii\Pricing\Domain\Quote\QuoteSource;
use App\Yoowii\Pricing\Domain\Quote\QuoteTrace;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Repository\SupplierPricingMatrixVersionRepository;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;
use Psr\Log\LoggerInterface;

final readonly class PrintQuoteService
{
    public function __construct(
        private SupplierRouteRepository $routeRepository,
        private SupplierPricingMatrixVersionRepository $matrixRepository,
        private MatrixPrintPriceCalculator $calculator,
        private FixedSupplierRouter $supplierRouter,
        private RealisaprintLiveQuoteCalculator $realisaprintCalculator,
        private LoggerInterface $logger,
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

        $correlationId = bin2hex(random_bytes(16));
        $fallbackReason = QuoteFallbackReason::MatrixUsed;
        $safeDetail = 'No eligible Realisaprint real-time quote route was selected.';
        foreach ($this->supplierRouter->rank($configuration->productCode(), $calculatedAt, $routes) as $route) {
            $reason = $this->realisaprintCalculator->fallbackReason($route);
            if (null !== $reason) {
                $fallbackReason = $reason;
                $safeDetail = 'Realisaprint real-time quotation is unavailable for this route.';
                break;
            }
            if (!$this->realisaprintCalculator->supports($route)) {
                continue;
            }
            try {
                return $this->realisaprintCalculator->quote($route, $configuration, $pricingPolicy, $currencyCode, $calculatedAt, $correlationId);
            } catch (RealisaprintQuoteException $exception) {
                $fallbackReason = $exception->reason();
                $safeDetail = $exception->safeDetail();
                break;
            }
        }

        $quote = $this->calculator->calculate(
            $configuration,
            $pricingPolicy,
            $currencyCode,
            $calculatedAt,
            $routes,
            $matrices,
        );
        $trace = new QuoteTrace(QuoteSource::MatrixFallback, $quote->supplierCode(), $quote->supplierProductCode(), null, null, $correlationId, $calculatedAt, $fallbackReason, $safeDetail);
        $this->logger->warning('Print quote fell back to matrix pricing.', [
            'correlation_id' => $correlationId,
            'source' => QuoteSource::MatrixFallback->value,
            'fallback_reason' => $fallbackReason->value,
            'supplier_code' => $quote->supplierCode(),
            'supplier_product_code' => $quote->supplierProductCode(),
            'technical_detail' => $safeDetail,
        ]);

        return $quote->withQuoteTrace($trace);
    }
}
