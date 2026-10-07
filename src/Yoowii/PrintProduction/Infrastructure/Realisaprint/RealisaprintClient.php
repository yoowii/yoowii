<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
     * Produces a copyable diagnostic command without disclosing credentials.
     *
     * @param array<string, mixed> $parameters
     */
    public function diagnosticCurl(string $operation, array $parameters): string
    {
        $arguments = [
            'curl --request POST ' . escapeshellarg(rtrim($this->baseUrl, '/') . '/' . rawurlencode($operation)),
            '--data-urlencode "shop_id=${YOOWII_REALISAPRINT_SHOP_ID}"',
            '--data-urlencode "api_key=${YOOWII_REALISAPRINT_API_KEY}"',
        ];
        foreach ($parameters as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $nestedValue) {
                    if (!is_scalar($nestedValue)) {
                        continue;
                    }
                    $arguments[] = '--data-urlencode ' . escapeshellarg(sprintf('%s[%s]=%s', $name, (string) $key, (string) $nestedValue));
                }

                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $arguments[] = '--data-urlencode ' . escapeshellarg(sprintf('%s=%s', $name, (string) $value));
        }

        return implode(" \\\n  ", $arguments);
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

        if (!is_array($decoded)) {
            return ['_http_status' => $response->getStatusCode(), '_malformed_response' => true];
        }
        /** @var array<string, mixed> $decoded */
        $decoded['_http_status'] = $response->getStatusCode();

        return $decoded;
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
