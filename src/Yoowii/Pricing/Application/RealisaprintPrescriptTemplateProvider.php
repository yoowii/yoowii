<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/** Retrieves the server-rendered Prescript template without exposing supplier credentials. */
final readonly class RealisaprintPrescriptTemplateProvider
{
    public function __construct(
        private RealisaprintClient $client,
        private CacheInterface $cache,
        private int $timeToLive,
    ) {
        if ($this->timeToLive < 15) {
            throw new \InvalidArgumentException('The Prescript template cache TTL must be at least 15 seconds.');
        }
    }

    public function template(string $product, string $stock, float $margin, string $country, string $saveConfigurationUrl): string
    {
        $product = trim($product);
        $stock = trim($stock);
        $country = strtoupper(trim($country));
        if ('' === $product || '' === $stock || 1 !== preg_match('/^[A-Z]{2}$/D', $country) || $margin < 1.0) {
            throw new \InvalidArgumentException('Invalid Prescript template parameters.');
        }

        // The callback route must be stable per linked product. Never include a
        // quote/session token here, otherwise a cached template could leak it.
        $key = 'yoowii.realisaprint.prescript.template.' . hash('sha256', implode('|', [$product, $stock, (string) $margin, $country, $saveConfigurationUrl]));

        return $this->cache->get($key, function (ItemInterface $item) use ($product, $stock, $margin, $country, $saveConfigurationUrl): string {
            $item->expiresAfter($this->timeToLive);

            return $this->client->postContent('get_prescript', [
                'product' => $product,
                'stock' => $stock,
                'margin' => $margin,
                'country' => $country,
                'save_configuration_url' => $saveConfigurationUrl,
            ]);
        });
    }
}
