<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Quote;

use App\Yoowii\Pricing\Domain\PricingSnapshot;

final readonly class StoredPrintQuote
{
    public function __construct(
        private string $variantCode,
        private string $definitionCode,
        private PricingSnapshot $pricingSnapshot,
        private \DateTimeImmutable $expiresAt,
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $this->variantCode)) {
            throw new \InvalidArgumentException('The print variant code is invalid.');
        }

        if (1 !== preg_match('/^PRINT_[A-Z0-9_]+$/D', $this->definitionCode)) {
            throw new \InvalidArgumentException('The print definition code is invalid.');
        }

        $configuration = $this->pricingSnapshot->toArray()['configuration'];

        if (($configuration['product_code'] ?? null) !== $this->definitionCode) {
            throw new \InvalidArgumentException('The print quote does not match its calculator definition.');
        }
    }

    public function variantCode(): string
    {
        return $this->variantCode;
    }

    public function definitionCode(): string
    {
        return $this->definitionCode;
    }

    public function pricingSnapshot(): PricingSnapshot
    {
        return $this->pricingSnapshot;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
