<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Configurator;

/** Explicit registry: adding a provider means adding one driver, not branching existing engines. */
final readonly class PrintConfiguratorDriverRegistry
{
    /** @var array<string, PrintConfiguratorDriver> */
    private array $drivers;

    public function __construct(
        MatrixPrintConfiguratorDriver $matrix,
        RealisaprintClassicConfiguratorDriver $realisaprintClassic,
        RealisaprintPrescriptConfiguratorDriver $realisaprintPrescript,
    ) {
        $this->drivers = [
            $matrix->code() => $matrix,
            $realisaprintClassic->code() => $realisaprintClassic,
            $realisaprintPrescript->code() => $realisaprintPrescript,
        ];
    }

    public function get(string $code): PrintConfiguratorDriver
    {
        return $this->drivers[$code] ?? throw new \DomainException(sprintf('Le driver de configurateur « %s » n’est pas installé.', $code));
    }
}
