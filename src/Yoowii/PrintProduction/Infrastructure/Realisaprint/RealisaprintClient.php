<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RealisaprintClient
{
    public function __construct(private HttpClientInterface $client, private string $shopId, private string $apiKey, private bool $enabled, private string $baseUrl)
    {
    }

    /** @param array<string, scalar|array<array-key, scalar>> $parameters @return array<string, mixed> */
    public function post(string $operation, array $parameters): array
    {
        if (!$this->enabled) {
            return ['simulation' => true, 'operation' => $operation, 'payload' => $this->redact($parameters)];
        }

        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/' . rawurlencode($operation), [
            'body' => ['shop_id' => $this->shopId, 'api_key' => $this->apiKey] + $parameters,
        ]);

        $body = $response->getContent(false);
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['http_status' => $response->getStatusCode(), 'body' => $body];
    }

    /** @param array<string, scalar|array<array-key, scalar>> $parameters @return array<string, scalar|array<array-key, scalar>> */
    private function redact(array $parameters): array
    {
        unset($parameters['api_key']);

        return $parameters;
    }
}
