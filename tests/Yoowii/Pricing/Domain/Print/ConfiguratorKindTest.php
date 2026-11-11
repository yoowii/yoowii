<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain\Print;

use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
use PHPUnit\Framework\TestCase;

final class ConfiguratorKindTest extends TestCase
{
    public function testItUsesTheMatrixRendererWithoutASupplierConfiguratorMapping(): void
    {
        self::assertSame(ConfiguratorKind::Matrix, ConfiguratorKind::fromConfigurationMapping([]));
    }

    public function testItUsesTheSupplierApiRendererForTheClassicRealisaprintConfigurator(): void
    {
        self::assertSame(
            ConfiguratorKind::SupplierApi,
            ConfiguratorKind::fromConfigurationMapping(['realisaprint' => ['configurator' => 'classic']]),
        );
    }

    public function testItUsesThePrescriptRendererOnlyWhenTheMappingExplicitlyRequestsIt(): void
    {
        self::assertSame(
            ConfiguratorKind::Prescript,
            ConfiguratorKind::fromConfigurationMapping(['realisaprint' => ['configurator' => 'prescript']]),
        );
    }
}
