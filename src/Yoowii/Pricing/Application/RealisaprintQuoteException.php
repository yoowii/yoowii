<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Quote\QuoteFallbackReason;

final class RealisaprintQuoteException extends \RuntimeException
{
    public function __construct(private readonly QuoteFallbackReason $reason, private readonly string $safeDetail)
    {
        parent::__construct($safeDetail);
    }

    public function reason(): QuoteFallbackReason { return $this->reason; }
    public function safeDetail(): string { return $this->safeDetail; }
}
