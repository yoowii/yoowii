<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use Symfony\Component\Validator\Constraints as Assert;

final class SupplierProductMappingData
{
    #[Assert\NotBlank]
    public string $yoowiiProductCode = 'PRINT_FLYER';

    #[Assert\NotNull]
    public ?SupplierProduct $supplierProduct = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $version = '';

    #[Assert\NotNull]
    public ?\DateTimeImmutable $effectiveFrom = null;

    #[Assert\NotBlank]
    public string $configurationMapping = "{\n  \"realisaprint\": {\n    \"product\": \"\",\n    \"stock\": \"\",\n    \"variables\": {}\n  }\n}";
}
