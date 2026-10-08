<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;

/** Produces the only canonical configuration allowed to reach Realisaprint. */
final class RealisaprintVisibleConfigurationBuilder
{
    /**
     * @param array{visibility?: array<string, bool>, availability?: array<string, list<string>>} $providerState
     */
    public function buildVisibleProviderConfiguration(PrintConfiguration $configuration, array $providerState): PrintConfiguration
    {
        $values = $configuration->toArray();
        $visibility = is_array($providerState['visibility'] ?? null) ? $providerState['visibility'] : [];
        $availability = is_array($providerState['availability'] ?? null) ? $providerState['availability'] : [];
        $visible = [];
        foreach ($values as $option => $value) {
            if (false === ($visibility[$option] ?? true)) {
                continue;
            }
            if (isset($availability[$option]) && !in_array((string) $value, $availability[$option], true)) {
                throw new \DomainException(sprintf('La valeur sélectionnée pour « %s » n’est plus disponible.', $option));
            }
            $visible[$option] = $value;
        }

        $visibleAxes = [];
        foreach ($configuration->pricingAxes() as $axis) {
            if (false === ($visibility[$axis] ?? true)) {
                continue;
            }
            if (!array_key_exists($axis, $visible) || '' === trim((string) $visible[$axis])) {
                throw new \DomainException(sprintf('Renseignez le champ requis « %s » avant de calculer le prix.', $axis));
            }
            if (isset($availability[$axis]) && [] === $availability[$axis]) {
                throw new \DomainException(sprintf('Aucun choix n’est disponible pour « %s ».', $axis));
            }
            $visibleAxes[] = $axis;
        }

        return new PrintConfiguration($configuration->productCode(), $configuration->schemaVersion(), $visible, $visibleAxes);
    }
}
