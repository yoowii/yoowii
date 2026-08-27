<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Exception;

use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;

final class PrintPriceNotFound extends \DomainException
{
    public function __construct(PrintConfiguration $configuration)
    {
        parent::__construct(sprintf(
            'No supplier price is available for product "%s" and configuration "%s".',
            $configuration->productCode(),
            $configuration->matrixKey(),
        ));
    }
}
