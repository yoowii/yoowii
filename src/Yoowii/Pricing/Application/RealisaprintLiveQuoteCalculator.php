<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\PricingSnapshot;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Pricing\Domain\Print\PrintPricingPolicy;
use App\Yoowii\Pricing\Domain\Print\PrintQuote;
use App\Yoowii\Pricing\Domain\Quote\QuoteFallbackReason;
use App\Yoowii\Pricing\Domain\Quote\QuoteSource;
use App\Yoowii\Pricing\Domain\Quote\QuoteTrace;
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

    public function fallbackReason(SupplierRoute $route): ?QuoteFallbackReason
    {
        $supplier = $route->supplierProduct()->supplier();
        if ('realisaprint' !== $supplier->code()) {
            return null;
        }
        if (!$this->enabled) {
            return QuoteFallbackReason::QuoteDisabled;
        }
        if (!$this->client->isEnabled()) {
            return QuoteFallbackReason::RealisaprintDisabled;
        }
        if (!in_array($supplier->integrationMode(), [SupplierIntegrationMode::Api, SupplierIntegrationMode::Hybrid], true) || !$supplier->supports(SupplierCapability::RealtimeQuote)) {
            return QuoteFallbackReason::SupplierNotEligible;
        }

        return null;
    }

    public function quote(SupplierRoute $route, PrintConfiguration $configuration, PrintPricingPolicy $pricingPolicy, string $currencyCode, \DateTimeImmutable $at, ?string $correlationId = null): PrintQuote
    {
        $correlationId ??= bin2hex(random_bytes(16));
        if ('EUR' !== $currencyCode) {
            throw new RealisaprintQuoteException(QuoteFallbackReason::SupplierNotEligible, 'Realisaprint quotation is only available in EUR.');
        }

        try {
            $mapped = $this->configurationMapper->map($configuration, $route->supplierProduct(), $at);
        } catch (\DomainException $exception) {
            $reason = str_contains($exception->getMessage(), 'No active') ? QuoteFallbackReason::MappingMissing : QuoteFallbackReason::MappingIncompatible;

            throw new RealisaprintQuoteException($reason, 'The active Realisaprint mapping cannot resolve this configuration.');
        }

        return $this->quoteMapped($route, $configuration, $pricingPolicy, $currencyCode, $at, $correlationId, $mapped);
    }

    /**
     * Quotes an inactive mapping during controlled publication. It never changes its eligibility.
     *
     * @param array<string, mixed> $mapping
     */
    public function quoteDraftMapping(SupplierRoute $route, PrintConfiguration $configuration, PrintPricingPolicy $pricingPolicy, string $currencyCode, \DateTimeImmutable $at, array $mapping, string $version): PrintQuote
    {
        if (!$this->supports($route)) {
            throw new RealisaprintQuoteException(QuoteFallbackReason::SupplierNotEligible, 'The Realisaprint supplier is not eligible for a live quote.');
        }
        if ('EUR' !== $currencyCode) {
            throw new RealisaprintQuoteException(QuoteFallbackReason::SupplierNotEligible, 'Realisaprint quotation is only available in EUR.');
        }

        try {
            $mapped = $this->configurationMapper->mapMapping($configuration, $mapping, $version);
        } catch (\DomainException $exception) {
            throw new RealisaprintQuoteException(QuoteFallbackReason::MappingIncompatible, 'The draft Realisaprint mapping cannot resolve this configuration.');
        }

        return $this->quoteMapped($route, $configuration, $pricingPolicy, $currencyCode, $at, bin2hex(random_bytes(16)), $mapped);
    }

    /** @param array{product: string, stock: string, variables: array<string, bool|float|int|string>, version: string, fingerprint: string} $mapped */
    private function quoteMapped(SupplierRoute $route, PrintConfiguration $configuration, PrintPricingPolicy $pricingPolicy, string $currencyCode, \DateTimeImmutable $at, string $correlationId, array $mapped): PrintQuote
    {
        $key = 'yoowii.realisaprint.quote.' . hash('sha256', implode('|', [$mapped['fingerprint'], $mapped['version'], $currencyCode]));
        $cacheMiss = false;

        try {
            $response = $this->cache->get($key, function (ItemInterface $item) use ($mapped, &$cacheMiss): array {
                $cacheMiss = true;
                $item->expiresAfter($this->cacheTtl);
                $saved = $this->client->post('save_configuration', [
                    'product' => $mapped['product'],
                    'stock' => $mapped['stock'],
                    'variables' => $mapped['variables'],
                ]);
                $code = $saved['code'] ?? null;
                if (!is_scalar($code) || '' === trim((string) $code)) {
                    throw new RealisaprintQuoteException(QuoteFallbackReason::ApiRejectedConfiguration, 'Realisaprint did not return a configuration code.');
                }
                $price = $this->client->post('get_price', ['code' => (string) $code, 'quantity' => 1, 'country' => 'FR']);
                if (isset($price['error'])) {
                    throw new RealisaprintQuoteException(QuoteFallbackReason::ApiPriceMissing, 'Realisaprint did not return a price.');
                }

                return ['configuration' => $saved, 'price' => $price];
            });
        } catch (\Throwable $exception) {
            if ($exception instanceof RealisaprintQuoteException) {
                throw $exception;
            }
            $message = strtolower($exception->getMessage());
            $reason = str_contains($message, 'rate limit') ? QuoteFallbackReason::RateLimited : (str_contains($message, 'timeout') ? QuoteFallbackReason::ApiTimeout : QuoteFallbackReason::ApiTransportError);

            throw new RealisaprintQuoteException($reason, 'Realisaprint API transport failed.');
        }

        $priceResponse = $response['price'];

        try {
            $productionCost = $this->cents($priceResponse['price'] ?? null, 'price');
            $optionsCost = 0;
            $options = $priceResponse['options'] ?? [];
            if (!is_array($options)) {
                throw new \DomainException('Invalid options.');
            }
            foreach ($options as $option) {
                if (is_array($option) && array_key_exists('price', $option)) {
                    $optionsCost += $this->cents($option['price'], 'option price');
                }
            }
        } catch (\DomainException) {
            throw new RealisaprintQuoteException(QuoteFallbackReason::ApiResponseInvalid, 'Realisaprint returned an invalid price response.');
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
                'provider_configuration_code' => is_scalar($response['configuration']['code'] ?? null) ? (string) $response['configuration']['code'] : '',
                'provider_price' => $productionCost,
                'provider_options_cost' => $optionsCost,
                'quote_cache_ttl' => $this->cacheTtl,
            ]]),
            ['provider_price' => $productionCost, 'provider_options' => $optionsCost, 'margin' => $margin, 'handling_fee' => $pricingPolicy->handlingFee(), 'total' => $total],
            $total,
            $currencyCode,
            $at,
            new QuoteTrace($cacheMiss ? QuoteSource::RealisaprintApi : QuoteSource::RealisaprintCache, $route->supplierProduct()->supplier()->code(), $route->supplierProduct()->code(), $mapped['version'], is_scalar($response['configuration']['code'] ?? null) ? (string) $response['configuration']['code'] : null, $correlationId, $at),
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

        return (int) round(((float) $normalized) * 100, 0, \PHP_ROUND_HALF_UP);
    }
}
