<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

/** The stable configurator identity for one Realisaprint catalogue product. */
final class RealisaprintPrintDefinitionCode
{
    public static function fromProviderProductId(string $providerProductId): string
    {
        $providerProductId = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $providerProductId) ?? '');
        $providerProductId = trim($providerProductId, '_');
        if ('' === $providerProductId) {
            throw new \DomainException('Le produit fournisseur ne permet pas de déterminer la définition de configurateur.');
        }

        if (45 < strlen($providerProductId)) {
            $providerProductId = substr($providerProductId, 0, 36) . '_' . substr(hash('sha256', $providerProductId), 0, 8);
        }

        return 'PRINT_REALISAPRINT_' . $providerProductId;
    }
}
