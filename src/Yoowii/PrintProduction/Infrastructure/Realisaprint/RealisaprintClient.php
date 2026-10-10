<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Application\RealisaprintRequestThrottle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RealisaprintClient
{
    public function __construct(
        private HttpClientInterface $client,
        private RealisaprintRequestThrottle $throttle,
        private string $shopId,
        private string $apiKey,
        private bool $enabled,
        private string $baseUrl,
        private int $idleTimeout,
        private int $maxDuration,
    ) {
        if ($this->idleTimeout <= 0 || $this->maxDuration < $this->idleTimeout) {
            throw new \InvalidArgumentException('Realisaprint HTTP timeouts must be positive and the maximum duration must exceed the idle timeout.');
        }
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

        $this->throttle->acquire($operation);

        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/' . rawurlencode($operation), [
            'body' => ['shop_id' => $this->shopId, 'api_key' => $this->apiKey] + $parameters,
            'timeout' => $this->idleTimeout,
            'max_duration' => $this->maxDuration,
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
     * Posts to a non-JSON Realisaprint endpoint, notably the Prescript HTML template.
     * Credentials are always injected server-side and never exposed to the browser.
     *
     * @param array<string, bool|float|int|string|array<array-key, bool|float|int|string>> $parameters
     */
    public function postContent(string $operation, array $parameters): string
    {
        if (!$this->enabled) {
            throw new \DomainException('Realisaprint is disabled.');
        }

        $this->throttle->acquire($operation);
        $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/' . rawurlencode($operation), [
            'body' => ['shop_id' => $this->shopId, 'api_key' => $this->apiKey] + $parameters,
            'timeout' => $this->idleTimeout,
            'max_duration' => $this->maxDuration,
        ]);
        $content = $response->getContent(false);
        if ($response->getStatusCode() >= 400 || '' === trim($content)) {
            throw new \RuntimeException(sprintf('Realisaprint did not return a Prescript template (HTTP %d).', $response->getStatusCode()));
        }

        return $content;
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
}
