<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;

/** Structural checks for the deliberately mapping-free Prescript publication path. */
final class RealisaprintPrescriptPublicationPolicy
{
    public function assertPublishable(SupplierProductMappingVersion $mapping, SupplierRoute $route, RealisaprintCatalogProduct $catalog): void
    {
        if ($route->supplierProduct() !== $mapping->supplierProduct() || $route->yoowiiProductCode() !== $mapping->yoowiiProductCode()) {
            throw new \DomainException('La route ne correspond pas au mapping Préscript à publier.');
        }

        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider) || 'prescript' !== ($provider['configurator'] ?? null)) {
            throw new \DomainException('Publication Préscript refusée : le mapping doit choisir explicitement le configurateur Préscript.');
        }

        $product = $provider['product'] ?? null;
        $stock = $provider['stock'] ?? null;
        if (!is_scalar($product) || '' === trim((string) $product) || !is_scalar($stock) || '' === trim((string) $stock)) {
            throw new \DomainException('Publication Préscript refusée : la référence fournisseur et le stock sont obligatoires.');
        }
        if ((string) $product !== $mapping->supplierProduct()->code() || (string) $product !== $catalog->providerProductId()) {
            throw new \DomainException('Publication Préscript refusée : la référence fournisseur ne correspond pas au mapping.');
        }
        if ($catalog->archived() || !$catalog->supportsPrescriptStock((string) $stock)) {
            throw new \DomainException('Publication Préscript refusée : ce stock n’est plus disponible dans le catalogue Préscript synchronisé.');
        }
    }
}
