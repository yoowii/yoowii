<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain\Exception;

final class AmbiguousSupplierRoute extends \DomainException
{
    public function __construct(string $productCode, int $priority)
    {
        parent::__construct(sprintf(
            'Several supplier routes have priority %d for product "%s".',
            $priority,
            $productCode,
        ));
    }
}
