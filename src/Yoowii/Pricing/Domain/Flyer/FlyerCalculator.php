<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Flyer;

use App\Yoowii\Pricing\Domain\Flyer\Exception\FlyerPriceNotFound;
use App\Yoowii\Pricing\Domain\Print\Exception\PrintPriceNotFound;
use App\Yoowii\Pricing\Domain\Print\MatrixPrintPriceCalculator;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

/**
 * Compatibility facade for the lot 2 flyer calculator.
 *
 * New print products must use MatrixPrintPriceCalculator.
 */
final readonly class FlyerCalculator
{
    private MatrixPrintPriceCalculator $calculator;

    public function __construct(FixedSupplierRouter $supplierRouter)
    {
        $this->calculator = new MatrixPrintPriceCalculator($supplierRouter);
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
        if ('PRINT_FLYER' !== $productCode) {
            throw new \InvalidArgumentException('FlyerCalculator only supports product "PRINT_FLYER".');
        }

        try {
            $quote = $this->calculator->calculate(
                $configuration->asPrintConfiguration(),
                $pricingPolicy->asPrintPricingPolicy(),
                $currencyCode,
                $calculatedAt,
                $routes,
                $matrices,
            );
        } catch (PrintPriceNotFound) {
            throw new FlyerPriceNotFound($productCode, $configuration);
        }

        return FlyerQuote::fromPrintQuote($quote);
    }
}
