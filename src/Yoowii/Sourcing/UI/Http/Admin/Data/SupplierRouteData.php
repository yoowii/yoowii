<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use Symfony\Component\Validator\Constraints as Assert;

final class SupplierRouteData
{
    #[Assert\NotBlank]
    public string $productCode = 'PRINT_FLYER';

    #[Assert\NotNull]
    public ?SupplierProduct $supplierProduct = null;

    #[Assert\Positive]
    public int $priority = 10;

    #[Assert\NotNull]
    public ?\DateTimeImmutable $effectiveFrom = null;

    #[Assert\GreaterThan(propertyPath: 'effectiveFrom')]
    public ?\DateTimeImmutable $effectiveUntil = null;

    public bool $active = true;
}
