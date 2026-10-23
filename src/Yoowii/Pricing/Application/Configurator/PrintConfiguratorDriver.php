<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Configurator;

/** Presentation capabilities of one isolated storefront configurator engine. */
interface PrintConfiguratorDriver
{
    public function code(): string;

    /** CSS/presentation key used by the shared Twig shell. */
    public function presentationMode(): string;

    public function usesSupplierRefresh(): bool;

    public function usesMatrixCombinations(): bool;

    public function usesSupplierDocument(): bool;
}
