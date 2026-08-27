<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain;

final readonly class PricingSnapshot
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, int> $priceBreakdown
     */
    public function __construct(
        private string $calculator,
        private string $pricingVersion,
        private array $configuration,
        private array $priceBreakdown,
        private int $unitPrice,
        private string $currencyCode,
        private \DateTimeImmutable $calculatedAt,
    ) {
        if ('' === trim($this->calculator)) {
            throw new \InvalidArgumentException('The calculator code must not be empty.');
        }

        if ('' === trim($this->pricingVersion)) {
            throw new \InvalidArgumentException('The pricing version must not be empty.');
        }

        if ($this->unitPrice < 0) {
            throw new \InvalidArgumentException('The unit price must be greater than or equal to zero.');
        }

        if (1 !== preg_match('/^[A-Z]{3}$/D', $this->currencyCode)) {
            throw new \InvalidArgumentException('The currency code must be a three-letter uppercase ISO code.');
        }

        if (!isset($this->priceBreakdown['total'])) {
            throw new \InvalidArgumentException('The price breakdown must contain a total.');
        }

        if ($this->unitPrice !== $this->priceBreakdown['total']) {
            throw new \InvalidArgumentException('The price breakdown total must match the unit price.');
        }

        self::normalizeConfiguration($this->configuration);
        self::normalizePriceBreakdown($this->priceBreakdown);
        self::assertJsonCompatible($this->configuration, 'configuration');
    }

    public function unitPrice(): int
    {
        return $this->unitPrice;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    /**
     * @return array{
     *     schema_version: int,
     *     calculator: string,
     *     pricing_version: string,
     *     configuration: array<string, mixed>,
     *     price_breakdown: array<string, int>,
     *     unit_price: int,
     *     currency_code: string,
     *     calculated_at: string
     * }
     */
    public function toArray(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'calculator' => $this->calculator,
            'pricing_version' => $this->pricingVersion,
            'configuration' => $this->configuration,
            'price_breakdown' => $this->priceBreakdown,
            'unit_price' => $this->unitPrice,
            'currency_code' => $this->currencyCode,
            'calculated_at' => $this->calculatedAt->format(\DateTimeInterface::ATOM),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (self::SCHEMA_VERSION !== ($data['schema_version'] ?? null)) {
            throw new \InvalidArgumentException('Unsupported pricing snapshot schema version.');
        }

        $calculator = $data['calculator'] ?? null;
        $pricingVersion = $data['pricing_version'] ?? null;
        $configuration = $data['configuration'] ?? null;
        $priceBreakdown = $data['price_breakdown'] ?? null;
        $unitPrice = $data['unit_price'] ?? null;
        $currencyCode = $data['currency_code'] ?? null;
        $calculatedAt = $data['calculated_at'] ?? null;

        if (
            !is_string($calculator) ||
            !is_string($pricingVersion) ||
            !is_array($configuration) ||
            !is_array($priceBreakdown) ||
            !is_int($unitPrice) ||
            !is_string($currencyCode) ||
            !is_string($calculatedAt)
        ) {
            throw new \InvalidArgumentException('The pricing snapshot payload is malformed.');
        }

        $normalizedConfiguration = self::normalizeConfiguration($configuration);
        $normalizedBreakdown = self::normalizePriceBreakdown($priceBreakdown);

        $calculatedAtDate = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $calculatedAt);

        if (false === $calculatedAtDate || $calculatedAt !== $calculatedAtDate->format(\DateTimeInterface::ATOM)) {
            throw new \InvalidArgumentException('The pricing snapshot calculation date is invalid.');
        }

        return new self(
            $calculator,
            $pricingVersion,
            $normalizedConfiguration,
            $normalizedBreakdown,
            $unitPrice,
            $currencyCode,
            $calculatedAtDate,
        );
    }

    /**
     * @param array<array-key, mixed> $configuration
     *
     * @return array<string, mixed>
     */
    private static function normalizeConfiguration(array $configuration): array
    {
        $normalized = [];

        foreach ($configuration as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('The pricing configuration must use string keys.');
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $priceBreakdown
     *
     * @return array<string, int>
     */
    private static function normalizePriceBreakdown(array $priceBreakdown): array
    {
        $normalized = [];

        foreach ($priceBreakdown as $label => $amount) {
            if (!is_string($label) || '' === trim($label) || !is_int($amount)) {
                throw new \InvalidArgumentException('The price breakdown must contain named integer amounts.');
            }

            $normalized[$label] = $amount;
        }

        return $normalized;
    }

    private static function assertJsonCompatible(mixed $value, string $path): void
    {
        if (null === $value || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new \InvalidArgumentException(sprintf('The value at "%s" is not JSON-compatible.', $path));
            }

            return;
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The value at "%s" is not JSON-compatible.', $path));
        }

        foreach ($value as $key => $nestedValue) {
            self::assertJsonCompatible($nestedValue, sprintf('%s.%s', $path, (string) $key));
        }
    }
}
