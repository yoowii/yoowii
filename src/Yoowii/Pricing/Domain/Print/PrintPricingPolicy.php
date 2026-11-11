<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print;

final readonly class PrintPricingPolicy
{
    public function __construct(
        private string $version,
        private int $markupBasisPoints,
        private int $minimumMargin,
        private int $handlingFee = 0,
    ) {
        if ('' === trim($this->version)) {
            throw new \InvalidArgumentException('The print pricing policy version must not be empty.');
        }

        if ($this->markupBasisPoints < 0 || $this->markupBasisPoints > 100000) {
            throw new \InvalidArgumentException('The markup must be between 0 and 100000 basis points.');
        }

        if ($this->minimumMargin < 0 || $this->handlingFee < 0) {
            throw new \InvalidArgumentException('Pricing policy amounts must be greater than or equal to zero.');
        }
    }

    public function version(): string
    {
        return $this->version;
    }

    public function calculateMargin(int $supplierCost): int
    {
        if ($supplierCost < 0) {
            throw new \InvalidArgumentException('The supplier cost must be greater than or equal to zero.');
        }

        $percentageMargin = intdiv(($supplierCost * $this->markupBasisPoints) + 9999, 10000);

        return max($percentageMargin, $this->minimumMargin);
    }

    public function handlingFee(): int
    {
        return $this->handlingFee;
    }
}
