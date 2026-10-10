<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\PricingSnapshot;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Quote\QuoteSource;
use App\Yoowii\Pricing\Domain\Quote\QuoteTrace;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;

/** Turns a configuration code produced by Prescript into an immutable Yoowii quote. */
final readonly class RealisaprintPrescriptQuoteCreator
{
    public function __construct(private RealisaprintClient $client)
    {
    }

    public function create(string $productCode, SupplierProductMappingVersion $mapping, string $configurationCode, int $quantity, PrintPricingPolicy $pricingPolicy, \DateTimeImmutable $at): PricingSnapshot
    {
        if ($quantity < 1 || '' === trim($configurationCode)) {
            throw new \InvalidArgumentException('La quantité ou le code de configuration Préscript est invalide.');
        }
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider) || 'prescript' !== ($provider['configurator'] ?? 'classic')) {
            throw new \DomainException('Le produit ne possède pas de mapping Préscript actif.');
        }
        $response = $this->client->post('get_price', ['code' => $configurationCode, 'quantity' => $quantity, 'country' => 'FR']);
        if (isset($response['error'])) {
            throw new \DomainException('Realisaprint ne peut pas calculer le prix de cette configuration.');
        }
        $base = $this->cents($response['price'] ?? null);
        $options = 0;
        foreach (is_array($response['options'] ?? null) ? $response['options'] : [] as $option) {
            if (is_array($option) && array_key_exists('price', $option)) {
                $options += $this->cents($option['price']);
            }
        }
        $supplierCost = $base + $options;
        $margin = $pricingPolicy->calculateMargin($supplierCost);
        $total = $supplierCost + $margin + $pricingPolicy->handlingFee();
        $correlationId = bin2hex(random_bytes(16));

        return new PricingSnapshot(
            'print.realisaprint_prescript',
            sprintf('%s@%s', $pricingPolicy->version(), $mapping->version()),
            [
                'product_code' => $productCode,
                'options' => [],
                'sourcing' => [
                    'supplier_code' => 'realisaprint',
                    'supplier_product_code' => $mapping->supplierProduct()->code(),
                    'mapping_version' => $mapping->version(),
                    'configurator' => 'prescript',
                    'provider_configuration_code' => $configurationCode,
                    'provider_quantity' => $quantity,
                    'provider_price' => $base,
                    'provider_options_cost' => $options,
                    'provider_details' => is_scalar($response['details'] ?? null) ? (string) $response['details'] : '',
                ],
            ],
            ['provider_price' => $base, 'provider_options' => $options, 'margin' => $margin, 'handling_fee' => $pricingPolicy->handlingFee(), 'total' => $total],
            $total,
            'EUR',
            $at,
            new QuoteTrace(QuoteSource::RealisaprintApi, 'realisaprint', $mapping->supplierProduct()->code(), $mapping->version(), $configurationCode, $correlationId, $at),
        );
    }

    private function cents(mixed $value): int
    {
        $normalized = is_scalar($value) ? str_replace(',', '.', (string) $value) : '';
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new \DomainException('Realisaprint a retourné un prix invalide.');
        }

        return (int) round((float) $normalized * 100, 0, \PHP_ROUND_HALF_UP);
    }
}
