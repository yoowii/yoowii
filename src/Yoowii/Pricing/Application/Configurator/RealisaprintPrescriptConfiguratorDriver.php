<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Configurator;

final class RealisaprintPrescriptConfiguratorDriver implements PrintConfiguratorDriver
{
    public function code(): string { return 'realisaprint_prescript'; }
    public function presentationMode(): string { return 'realisaprint-prescript'; }
    public function usesSupplierRefresh(): bool { return false; }
    public function usesMatrixCombinations(): bool { return false; }
    public function usesSupplierDocument(): bool { return true; }
}
