<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class PricingMatrixImportData
{
    #[Assert\NotBlank]
    public string $productCode = 'PRINT_FLYER';

    #[Assert\NotNull]
    public ?SupplierProduct $supplierProduct = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    public string $version = '';

    #[Assert\Currency]
    public string $currencyCode = 'EUR';

    #[Assert\NotNull]
    public ?\DateTimeImmutable $effectiveFrom = null;

    #[Assert\NotNull]
    #[Assert\File(maxSize: '5M')]
    public ?UploadedFile $file = null;

    public bool $activate = false;
}
