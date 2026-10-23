<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Configurator;

final class MatrixPrintConfiguratorDriver implements PrintConfiguratorDriver
{
    public function code(): string { return 'matrix'; }
    public function presentationMode(): string { return 'matrix-print'; }
    public function usesSupplierRefresh(): bool { return false; }
    public function usesMatrixCombinations(): bool { return true; }
    public function usesSupplierDocument(): bool { return false; }
}
