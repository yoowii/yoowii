<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Exception;

final class PricingSnapshotCurrencyMismatch extends \DomainException
{
    public function __construct(string $snapshotCurrency, string $orderCurrency)
    {
        parent::__construct(sprintf(
            'The pricing snapshot currency "%s" does not match the order currency "%s".',
            $snapshotCurrency,
            $orderCurrency,
        ));
    }
}
