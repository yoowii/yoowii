<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use Symfony\Component\Validator\Constraints as Assert;

final class SupplierProductData
{
    #[Assert\NotNull]
    public ?PrintSupplier $supplier = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    public bool $active = true;

    public static function fromProduct(SupplierProduct $product): self
    {
        $data = new self();
        $data->supplier = $product->supplier();
        $data->code = $product->code();
        $data->name = $product->name();
        $data->active = $product->isActive();

        return $data;
    }
}
