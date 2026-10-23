<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Configurator;

final class RealisaprintClassicConfiguratorDriver implements PrintConfiguratorDriver
{
    public function code(): string { return 'realisaprint_classic'; }
    public function presentationMode(): string { return 'realisaprint-classic'; }
    public function usesSupplierRefresh(): bool { return true; }
    public function usesMatrixCombinations(): bool { return false; }
    public function usesSupplierDocument(): bool { return false; }
}
