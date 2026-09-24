<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class RealisaprintClient
{
    public function __construct(private HttpClientInterface $client, private CacheInterface $cache, private string $shopId, private string $apiKey, private bool $enabled, private string $baseUrl)
    {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, bool|float|int|string|array<array-key, bool|float|int|string>> $parameters
     *
     * @return array<string, mixed>
     */
    public function post(string $operation, array $parameters): array
    {
        if (!$this->enabled) {
            return ['simulation' => true, 'operation' => $operation, 'payload' => $this->redact($parameters)];
        }

        $this->guardInterval($operation);

        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/' . rawurlencode($operation), [
            'body' => ['shop_id' => $this->shopId, 'api_key' => $this->apiKey] + $parameters,
        ]);

        $body = $response->getContent(false);
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['http_status' => $response->getStatusCode(), 'body' => $body];
    }

    /**
     * @param array<string, bool|float|int|string|array<array-key, bool|float|int|string>> $parameters
     *
     * @return array<string, bool|float|int|string|array<array-key, bool|float|int|string>>
     */
    private function redact(array $parameters): array
    {
        unset($parameters['api_key']);

        return $parameters;
    }

    private function guardInterval(string $operation): void
    {
        $reserved = false;
        $this->cache->get('yoowii.realisaprint.rate_limit.' . hash('sha256', $operation), function (ItemInterface $item) use (&$reserved): bool {
            $reserved = true;
            $item->expiresAfter(15);

            return true;
        });
        if (!$reserved) {
            throw new \RuntimeException(sprintf('Realisaprint rate limit: wait 15 seconds before calling "%s" again.', $operation));
        }
    }
}
