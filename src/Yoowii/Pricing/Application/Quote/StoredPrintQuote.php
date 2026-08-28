<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Quote;

use App\Yoowii\Pricing\Domain\PricingSnapshot;

final readonly class StoredPrintQuote
{
    public function __construct(
        private string $variantCode,
        private PricingSnapshot $pricingSnapshot,
        private \DateTimeImmutable $expiresAt,
    ) {
        if (1 !== preg_match('/^[A-Z][A-Z0-9_]*$/D', $this->variantCode)) {
            throw new \InvalidArgumentException('The print variant code is invalid.');
        }

        $configuration = $this->pricingSnapshot->toArray()['configuration'];

        if (($configuration['product_code'] ?? null) !== $this->variantCode) {
            throw new \InvalidArgumentException('The print quote does not match its catalog variant.');
        }
    }

    public function variantCode(): string
    {
        return $this->variantCode;
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
