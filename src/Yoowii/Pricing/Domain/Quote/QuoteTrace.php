<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Quote;

final readonly class QuoteTrace
{
    public function __construct(
        private QuoteSource $source,
        private string $supplierCode,
        private string $supplierProductCode,
        private ?string $mappingVersion,
        private ?string $providerConfigurationCode,
        private string $correlationId,
        private \DateTimeImmutable $calculatedAt,
        private ?QuoteFallbackReason $fallbackReason = null,
        private ?string $technicalDetail = null,
    ) {
        if ('' === trim($this->supplierCode) || '' === trim($this->supplierProductCode) || '' === trim($this->correlationId)) {
            throw new \InvalidArgumentException('Quote trace identifiers are required.');
        }
        if (QuoteSource::MatrixFallback === $this->source && null === $this->fallbackReason) {
            throw new \InvalidArgumentException('A matrix fallback requires a reason.');
        }
        if (QuoteSource::MatrixFallback !== $this->source && null !== $this->fallbackReason) {
            throw new \InvalidArgumentException('An API quote cannot have a fallback reason.');
        }
        if (null !== $this->technicalDetail && (str_contains(strtolower($this->technicalDetail), 'api_key') || str_contains(strtolower($this->technicalDetail), 'password'))) {
            throw new \InvalidArgumentException('Quote trace technical detail must not contain secrets.');
        }
        if (null !== $this->technicalDetail && mb_strlen($this->technicalDetail) > 280) {
            throw new \InvalidArgumentException('Quote trace technical detail must be limited to 280 characters.');
        }
    }

    public function source(): QuoteSource
    {
        return $this->source;
    }

    public function fallbackReason(): ?QuoteFallbackReason
    {
        return $this->fallbackReason;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function technicalDetail(): ?string
    {
        return $this->technicalDetail;
    }

    /** @return array<string, string|null> */
    public function toArray(): array
    {
        return [
            'source' => $this->source->value,
            'supplier_code' => $this->supplierCode,
            'supplier_product_code' => $this->supplierProductCode,
            'mapping_version' => $this->mappingVersion,
            'provider_configuration_code' => $this->providerConfigurationCode,
            'correlation_id' => $this->correlationId,
            'calculated_at' => $this->calculatedAt->format(\DateTimeInterface::ATOM),
            'fallback_reason' => $this->fallbackReason?->value,
            'technical_detail' => $this->technicalDetail,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $source = isset($data['source']) && is_string($data['source']) ? QuoteSource::tryFrom($data['source']) : null;
        $reason = isset($data['fallback_reason']) && is_string($data['fallback_reason']) ? QuoteFallbackReason::tryFrom($data['fallback_reason']) : null;
        $at = isset($data['calculated_at']) && is_string($data['calculated_at']) ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['calculated_at']) : false;
        if (null === $source || false === $at || !is_string($data['supplier_code'] ?? null) || !is_string($data['supplier_product_code'] ?? null) || !is_string($data['correlation_id'] ?? null)) {
            throw new \InvalidArgumentException('The quote trace payload is malformed.');
        }

        return new self($source, $data['supplier_code'], $data['supplier_product_code'], is_string($data['mapping_version'] ?? null) ? $data['mapping_version'] : null, is_string($data['provider_configuration_code'] ?? null) ? $data['provider_configuration_code'] : null, $data['correlation_id'], $at, $reason, is_string($data['technical_detail'] ?? null) ? $data['technical_detail'] : null);
    }
}
