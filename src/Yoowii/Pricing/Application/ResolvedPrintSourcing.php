<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

/**
 * The single sourcing decision used to render one storefront print product.
 *
 * It deliberately contains one route only: alternative suppliers remain a
 * back-office concern until the next customer configuration is started.
 */
final readonly class ResolvedPrintSourcing
{
    public function __construct(
        private SupplierRoute $route,
        private ConfiguratorKind $configuratorKind,
        private string $configuratorDriver,
        private ?SupplierProductMappingVersion $mapping = null,
    ) {
    }

    public function route(): SupplierRoute
    {
        return $this->route;
    }

    public function configuratorKind(): ConfiguratorKind
    {
        return $this->configuratorKind;
    }

    public function configuratorDriver(): string
    {
        return $this->configuratorDriver;
    }

    public function mapping(): ?SupplierProductMappingVersion
    {
        return $this->mapping;
    }

    /**
     * The schema selected by this supplier mapping. Matrix-only routes may
     * deliberately omit it and keep using the product's default schema.
     */
    public function definitionCode(): ?string
    {
        return $this->mapping?->printProductDefinition()?->productCode();
    }
}
