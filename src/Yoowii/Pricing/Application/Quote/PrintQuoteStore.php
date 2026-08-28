<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Quote;

use App\Yoowii\Pricing\Domain\PricingSnapshot;

interface PrintQuoteStore
{
    public function issue(
        string $variantCode,
        PricingSnapshot $pricingSnapshot,
        \DateTimeImmutable $now,
    ): string;

    public function find(string $token, \DateTimeImmutable $now): StoredPrintQuote;

    public function consume(string $token, \DateTimeImmutable $now): StoredPrintQuote;
}
