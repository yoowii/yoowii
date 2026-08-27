<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print;

final readonly class PrintConfiguration
{
    /**
     * @param array<string, string|int> $options
     * @param non-empty-list<string> $pricingAxes
     *
     * @internal Construct through PrintProductDefinition::configure().
     */
    public function __construct(
        private string $productCode,
        private string $schemaVersion,
        private array $options,
        private array $pricingAxes,
    ) {
    }

    public function productCode(): string
    {
        return $this->productCode;
    }

    public function schemaVersion(): string
    {
        return $this->schemaVersion;
    }

    /** @return array<string, string|int> */
    public function toArray(): array
    {
        return $this->options;
    }

    /**
     * @return array{
     *     product_code: string,
     *     schema_version: string,
     *     options: array<string, string|int>
     * }
     */
    public function snapshotData(): array
    {
        return [
            'product_code' => $this->productCode,
            'schema_version' => $this->schemaVersion,
            'options' => $this->options,
        ];
    }

    public function matrixKey(): string
    {
        $values = [];

        foreach ($this->pricingAxes as $axis) {
            if (!array_key_exists($axis, $this->options)) {
                throw new \LogicException(sprintf('Pricing axis "%s" is missing from the configuration.', $axis));
            }

            $values[] = (string) $this->options[$axis];
        }

        return implode('|', $values);
    }
}
