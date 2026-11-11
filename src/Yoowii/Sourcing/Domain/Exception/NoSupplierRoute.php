<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Exception;

final class NoSupplierRoute extends \DomainException
{
    public function __construct(string $productCode)
    {
        parent::__construct(sprintf('No supplier route is available for product "%s".', $productCode));
    }
}
