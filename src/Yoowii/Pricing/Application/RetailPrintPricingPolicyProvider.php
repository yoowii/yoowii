<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;

final readonly class RetailPrintPricingPolicyProvider
{
    public function __construct(
        private string $version,
        private int $markupBasisPoints,
        private int $minimumMargin,
        private int $handlingFee,
    ) {
    }

    public function get(): PrintPricingPolicy
    {
        return new PrintPricingPolicy(
            $this->version,
            $this->markupBasisPoints,
            $this->minimumMargin,
            $this->handlingFee,
        );
    }
}
