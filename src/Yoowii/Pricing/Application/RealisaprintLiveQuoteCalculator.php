<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\PricingSnapshot;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Print\PrintQuote;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\SupplierCapability;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RealisaprintLiveQuoteCalculator
{
    public function __construct(
        private RealisaprintConfigurationMapper $configurationMapper,
        private RealisaprintClient $client,
        private CacheInterface $cache,
        private int $cacheTtl,
        private bool $enabled,
    ) {
        if ($this->cacheTtl < 15) {
            throw new \InvalidArgumentException('The Realisaprint quote cache TTL must be at least 15 seconds.');
        }
    }

    public function supports(SupplierRoute $route): bool
    {
        $supplier = $route->supplierProduct()->supplier();

        return $this->enabled && 'realisaprint' === $supplier->code() &&
            $this->client->isEnabled() &&
            in_array($supplier->integrationMode(), [SupplierIntegrationMode::Api, SupplierIntegrationMode::Hybrid], true) &&
            $supplier->supports(SupplierCapability::RealtimeQuote);
    }

    public function quote(SupplierRoute $route, PrintConfiguration $configuration, PrintPricingPolicy $pricingPolicy, string $currencyCode, \DateTimeImmutable $at): PrintQuote
    {
        if ('EUR' !== $currencyCode) {
            throw new \DomainException('Realisaprint API quotation is only available in EUR.');
        }
        $mapped = $this->configurationMapper->map($configuration, $route->supplierProduct(), $at);
        $key = 'yoowii.realisaprint.quote.' . hash('sha256', implode('|', [$mapped['fingerprint'], $mapped['version'], $currencyCode]));
        $response = $this->cache->get($key, function (ItemInterface $item) use ($mapped): array {
            $item->expiresAfter($this->cacheTtl);
            $saved = $this->client->post('save_configuration', [
                'product' => $mapped['product'],
                'stock' => $mapped['stock'],
                'variables' => $mapped['variables'],
            ]);
            $code = $saved['code'] ?? null;
            if (!is_scalar($code) || '' === trim((string) $code)) {
                throw new \DomainException($this->error($saved, 'Realisaprint did not return a configuration code.'));
            }
            $price = $this->client->post('get_price', ['code' => (string) $code, 'quantity' => 1, 'country' => 'FR']);
            if (isset($price['error'])) {
                throw new \DomainException($this->error($price, 'Realisaprint did not return a price.'));
            }

            return ['configuration' => $saved, 'price' => $price];
        });

        $priceResponse = $response['price'] ?? null;
        if (!is_array($priceResponse)) {
            throw new \DomainException('Realisaprint returned a malformed price response.');
        }
        $productionCost = $this->cents($priceResponse['price'] ?? null, 'price');
        $optionsCost = 0;
        foreach (($priceResponse['options'] ?? []) as $option) {
            if (is_array($option) && array_key_exists('price', $option)) {
                $optionsCost += $this->cents($option['price'], 'option price');
            }
        }
        $supplierCost = $productionCost + $optionsCost;
        $margin = $pricingPolicy->calculateMargin($supplierCost);
        $total = $supplierCost + $margin + $pricingPolicy->handlingFee();
        $snapshot = new PricingSnapshot(
            'print.realisaprint_api',
            sprintf('%s@%s', $pricingPolicy->version(), $mapped['version']),
            array_merge($configuration->snapshotData(), ['sourcing' => [
                'supplier_code' => $route->supplierProduct()->supplier()->code(),
                'supplier_product_code' => $route->supplierProduct()->code(),
                'mapping_version' => $mapped['version'],
                'configuration_fingerprint' => $mapped['fingerprint'],
                'provider_configuration_code' => (string) (($response['configuration']['code'] ?? '')),
                'provider_price' => $productionCost,
                'provider_options_cost' => $optionsCost,
                'quote_cache_ttl' => $this->cacheTtl,
            ]]),
            ['provider_price' => $productionCost, 'provider_options' => $optionsCost, 'margin' => $margin, 'handling_fee' => $pricingPolicy->handlingFee(), 'total' => $total],
            $total,
            $currencyCode,
            $at,
        );

        return new PrintQuote($snapshot, 'realisaprint', $route->supplierProduct()->code(), 'api:' . $mapped['version'], $mapped['fingerprint'], $productionCost, $optionsCost, $margin, $pricingPolicy->handlingFee());
    }

    private function cents(mixed $value, string $label): int
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new \DomainException(sprintf('Realisaprint returned an invalid %s.', $label));
        }
        $normalized = str_replace(',', '.', (string) $value);
        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new \DomainException(sprintf('Realisaprint returned an invalid %s.', $label));
        }

        return (int) round(((float) $normalized) * 100, 0, PHP_ROUND_HALF_UP);
    }

    /** @param array<string, mixed> $response */
    private function error(array $response, string $fallback): string
    {
        return is_string($response['error'] ?? null) && '' !== trim($response['error']) ? $response['error'] : $fallback;
    }
}
