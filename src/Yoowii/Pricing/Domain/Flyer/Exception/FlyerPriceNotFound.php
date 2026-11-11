<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Flyer\Exception;

use App\Yoowii\Pricing\Domain\Flyer\FlyerConfiguration;

final class FlyerPriceNotFound extends \DomainException
{
    public function __construct(string $productCode, FlyerConfiguration $configuration)
    {
        parent::__construct(sprintf(
            'No supplier price is available for product "%s" and flyer configuration "%s".',
            $productCode,
            $configuration->matrixKey(),
        ));
    }
}
