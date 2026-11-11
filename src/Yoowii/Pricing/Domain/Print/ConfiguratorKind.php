<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print;

/**
 * The storefront renderer used by a print product.
 *
 * This is deliberately independent from a supplier name: a supplier can
 * expose a native API for one product and a Prescript document for another.
 */
enum ConfiguratorKind: string
{
    case Matrix = 'matrix';
    case SupplierApi = 'supplier_api';
    case Prescript = 'prescript';

    /**
     * @param array<string, mixed> $configurationMapping
     */
    public static function fromConfigurationMapping(array $configurationMapping): self
    {
        $realisaprint = $configurationMapping['realisaprint'] ?? null;

        if (!is_array($realisaprint)) {
            return self::Matrix;
        }

        return 'prescript' === ($realisaprint['configurator'] ?? 'classic')
            ? self::Prescript
            : self::SupplierApi;
    }
}
