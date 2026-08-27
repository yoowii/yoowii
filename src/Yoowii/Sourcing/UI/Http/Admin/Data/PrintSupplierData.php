<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\SupplierCapability;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Symfony\Component\Validator\Constraints as Assert;

final class PrintSupplierData
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9][a-z0-9_-]*$/D')]
    public string $code = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    public SupplierIntegrationMode $integrationMode = SupplierIntegrationMode::Matrix;

    /** @var list<SupplierCapability> */
    public array $capabilities = [];

    public bool $active = true;

    public static function fromSupplier(PrintSupplier $supplier): self
    {
        $data = new self();
        $data->code = $supplier->code();
        $data->name = $supplier->name();
        $data->integrationMode = $supplier->integrationMode();
        $data->capabilities = $supplier->capabilities();
        $data->active = $supplier->isActive();

        return $data;
    }
}
