<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application\Configurator;

use App\Yoowii\Pricing\Application\Configurator\MatrixPrintConfiguratorDriver;
use App\Yoowii\Pricing\Application\Configurator\PrintConfiguratorDriverRegistry;
use App\Yoowii\Pricing\Application\Configurator\RealisaprintClassicConfiguratorDriver;
use App\Yoowii\Pricing\Application\Configurator\RealisaprintPrescriptConfiguratorDriver;
use PHPUnit\Framework\TestCase;

final class PrintConfiguratorDriverRegistryTest extends TestCase
{
    public function testItKeepsTheThreeCurrentEnginesIsolatedBehindExplicitCapabilities(): void
    {
        $registry = new PrintConfiguratorDriverRegistry(
            new MatrixPrintConfiguratorDriver(),
            new RealisaprintClassicConfiguratorDriver(),
            new RealisaprintPrescriptConfiguratorDriver(),
        );

        self::assertTrue($registry->get('matrix')->usesMatrixCombinations());
        self::assertTrue($registry->get('realisaprint_classic')->usesSupplierRefresh());
        self::assertTrue($registry->get('realisaprint_prescript')->usesSupplierDocument());
        self::assertSame('realisaprint-classic', $registry->get('realisaprint_classic')->presentationMode());
    }

    public function testItRejectsAnUninstalledDriver(): void
    {
        $registry = new PrintConfiguratorDriverRegistry(
            new MatrixPrintConfiguratorDriver(),
            new RealisaprintClassicConfiguratorDriver(),
            new RealisaprintPrescriptConfiguratorDriver(),
        );

        $this->expectException(\DomainException::class);
        $registry->get('future_supplier_api');
    }
}
