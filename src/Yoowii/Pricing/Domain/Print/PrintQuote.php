<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print;

use App\Yoowii\Pricing\Domain\PricingSnapshot;

final readonly class PrintQuote
{
    public function __construct(
        private PricingSnapshot $pricingSnapshot,
        private string $supplierCode,
        private string $supplierProductCode,
        private string $matrixVersion,
        private string $matrixChecksum,
        private int $productionCost,
        private int $shippingCost,
        private int $margin,
        private int $handlingFee,
    ) {
    }

    public function pricingSnapshot(): PricingSnapshot
    {
        return $this->pricingSnapshot;
    }

    public function supplierCode(): string
    {
        return $this->supplierCode;
    }

    public function supplierProductCode(): string
    {
        return $this->supplierProductCode;
    }

    public function matrixVersion(): string
    {
        return $this->matrixVersion;
    }

    public function matrixChecksum(): string
    {
        return $this->matrixChecksum;
    }

    public function productionCost(): int
    {
        return $this->productionCost;
    }

    public function shippingCost(): int
    {
        return $this->shippingCost;
    }

    public function supplierCost(): int
    {
        return $this->productionCost + $this->shippingCost;
    }

    public function margin(): int
    {
        return $this->margin;
    }

    public function handlingFee(): int
    {
        return $this->handlingFee;
    }
}
