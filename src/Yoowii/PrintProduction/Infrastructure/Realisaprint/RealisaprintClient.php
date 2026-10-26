<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Application\RealisaprintRequestThrottle;
use Psr\Log\LoggerInterface;
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
        private LoggerInterface $logger,
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

        $lease = $this->throttle->acquire();

        try {
            $response = $this->client->request('POST', rtrim($this->baseUrl, '/') . '/' . rawurlencode($operation), [
                'body' => ['shop_id' => $this->shopId, 'api_key' => $this->apiKey] + $parameters,
                'timeout' => $this->idleTimeout,
                'max_duration' => $this->maxDuration,
            ]);

            $body = $response->getContent(false);
            $status = $response->getStatusCode();
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                $this->logger->warning('Realisaprint API returned a non-JSON response.', [
                    'operation' => $operation,
                    'http_status' => $status,
                    'request' => $this->requestSummary($parameters),
                    'response_length' => strlen($body),
                    'response_fingerprint' => hash('sha256', $body),
                ]);

                return ['_http_status' => $status, '_malformed_response' => true];
            }
            /** @var array<string, mixed> $configuration */
            $configuration = $decoded;
            $configuration['_http_status'] = $status;
            if ($status < 200 || $status >= 300 || $this->hasSupplierError($configuration)) {
                $this->logger->warning('Realisaprint API rejected a request.', [
                    'operation' => $operation,
                    'http_status' => $status,
                    'request' => $this->requestSummary($parameters),
                    'response_keys' => array_keys($configuration),
                    'supplier_error' => $this->supplierErrorSummary($configuration['error'] ?? null),
                ]);
            }

            return $configuration;
        } catch (\Throwable $exception) {
            $this->logger->error('Realisaprint API transport failed.', [
                'operation' => $operation,
                'request' => $this->requestSummary($parameters),
                'failure_class' => $exception::class,
            ]);

            throw $exception;
        } finally {
            $this->throttle->complete($lease);
        }
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

        $lease = $this->throttle->acquire();

        try {
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
        } finally {
            $this->throttle->complete($lease);
        }
    }

    /** @return array{content: string, mime_type: string} */
    public function downloadPicture(string $url): array
    {
        if (!self::isPictureUrl($url)) {
            throw new \InvalidArgumentException('L’image sélectionnée ne provient pas du catalogue Realisaprint.');
        }

        $response = $this->client->request('GET', $url, [
            'timeout' => $this->idleTimeout,
            'max_duration' => $this->maxDuration,
            'max_redirects' => 0,
        ]);
        $content = $response->getContent(false);
        $status = $response->getStatusCode();
        $headers = $response->getHeaders(false);
        $mimeType = strtolower(trim(explode(';', (string) (($headers['content-type'][0] ?? '')))[0]));
        if ($status < 200 || $status >= 300 || '' === $content || strlen($content) > 8 * 1024 * 1024 || !in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \DomainException('Le téléchargement de l’image Realisaprint a échoué.');
        }

        return ['content' => $content, 'mime_type' => $mimeType];
    }

    public static function isPictureUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts) || 'https' !== ($parts['scheme'] ?? null) || !is_string($parts['host'] ?? null) || !is_string($parts['path'] ?? null)) {
            return false;
        }

        return in_array(strtolower($parts['host']), ['www.realisaprint.com', 'realisaprint.com'], true)
            && str_starts_with($parts['path'], '/api_img/');
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

    /** @param array<string, mixed> $response */
    private function hasSupplierError(array $response): bool
    {
        return array_key_exists('error', $response) && !in_array($response['error'], [false, null, ''], true);
    }

    /** @param array<string, bool|float|int|string|array<array-key, bool|float|int|string>> $parameters
     *  @return array<string, mixed>
     */
    private function requestSummary(array $parameters): array
    {
        $summary = [];
        foreach (['product', 'stock', 'code', 'quantity', 'country'] as $name) {
            if (isset($parameters[$name]) && is_scalar($parameters[$name])) {
                $summary[$name] = (string) $parameters[$name];
            }
        }
        if (isset($parameters['variables']) && is_array($parameters['variables'])) {
            $variables = [];
            foreach ($parameters['variables'] as $name => $value) {
                $variables[(string) $name] = [
                    'length' => strlen((string) $value),
                    'fingerprint' => substr(hash('sha256', (string) $value), 0, 16),
                ];
            }
            ksort($variables);
            $summary['variables'] = $variables;
            $summary['variables_fingerprint'] = hash('sha256', serialize($variables));
        }

        return $summary;
    }

    /** @return array{type: string, message?: string} */
    private function supplierErrorSummary(mixed $error): array
    {
        if (!is_scalar($error)) {
            return ['type' => get_debug_type($error)];
        }
        $message = mb_substr(trim((string) $error), 0, 500);
        $message = (string) preg_replace('#https?://[^\s]+#i', '[url-redacted]', $message);
        $message = (string) preg_replace('/\b(api[_-]?key|token|secret|password)\b\s*[:=]\s*[^\s,;]+/i', '$1=[redacted]', $message);

        return ['type' => get_debug_type($error), 'message' => $message];
    }
}
