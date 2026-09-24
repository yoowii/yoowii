<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;

/** Resolves canonical Yoowii options to an active Realisaprint configuration. */
final readonly class RealisaprintConfigurationMapper
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return array{product: string, stock: string, variables: array<string, bool|float|int|string>, version: string, fingerprint: string} */
    public function map(PrintConfiguration $configuration, SupplierProduct $supplierProduct, \DateTimeImmutable $at): array
    {
        $mapping = $this->mapping($configuration->productCode(), $supplierProduct, $at);
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;

        if (!is_array($provider)) {
            throw new \DomainException('The active supplier mapping does not contain a Realisaprint configuration.');
        }

        $product = $provider['product'] ?? null;
        $stock = $provider['stock'] ?? null;
        $rules = $provider['variables'] ?? null;
        if (!is_scalar($product) || !is_scalar($stock) || !is_array($rules)) {
            throw new \DomainException('The active Realisaprint mapping must define product, stock and variables.');
        }

        /** @var array<string, mixed> $typedRules */
        $typedRules = $rules;
        $variables = $this->variables($typedRules, $configuration->toArray());
        $payload = ['product' => (string) $product, 'stock' => (string) $stock, 'variables' => $variables];

        return $payload + [
            'version' => $mapping->version(),
            'fingerprint' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array<string, list<string|int>> */
    public function catalogOptions(string $yoowiiProductCode, SupplierProduct $supplierProduct, \DateTimeImmutable $at): array
    {
        $mapping = $this->mapping($yoowiiProductCode, $supplierProduct, $at);
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        $rules = is_array($provider) ? ($provider['variables'] ?? null) : null;
        if (!is_array($rules)) {
            return [];
        }

        $options = [];
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['option']) || !is_string($rule['option'])) {
                continue;
            }
            $values = $rule['catalog_values'] ?? ($rule['values'] ?? null);
            if (!is_array($values)) {
                continue;
            }
            foreach (array_is_list($values) ? $values : array_keys($values) as $value) {
                if (is_string($value) || is_int($value)) {
                    $options[$rule['option']][get_debug_type($value) . ':' . $value] = $value;
                }
            }
        }

        /** @var array<string, list<string|int>> $normalized */
        $normalized = [];
        foreach ($options as $option => $values) {
            $normalized[$option] = array_values($values);
        }

        return $normalized;
    }

    private function mapping(string $yoowiiProductCode, SupplierProduct $supplierProduct, \DateTimeImmutable $at): SupplierProductMappingVersion
    {
        /** @var list<SupplierProductMappingVersion> $mappings */
        $mappings = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([
            'yoowiiProductCode' => $yoowiiProductCode,
            'active' => true,
        ]);
        foreach ($mappings as $mapping) {
            if ($mapping->supplierProduct() === $supplierProduct && $mapping->isEffectiveAt($at)) {
                return $mapping;
            }
        }

        throw new \DomainException('No active Realisaprint configuration mapping exists for this supplier product.');
    }

    /** @param array<string, mixed> $rules @param array<string, string|int> $options @return array<string, bool|float|int|string> */
    private function variables(array $rules, array $options): array
    {
        $variables = [];
        foreach ($rules as $variable => $rule) {
            if (!is_string($variable) || '' === $variable) {
                throw new \DomainException('A Realisaprint variable identifier is invalid.');
            }
            if (is_scalar($rule)) {
                $variables[$variable] = $rule;

                continue;
            }
            if (!is_array($rule) || !isset($rule['option']) || !is_string($rule['option'])) {
                throw new \DomainException(sprintf('The mapping for Realisaprint variable "%s" is invalid.', $variable));
            }
            $value = $options[$rule['option']] ?? null;
            $values = $rule['values'] ?? null;
            if (is_array($values) && is_scalar($value) && array_key_exists((string) $value, $values)) {
                $value = $values[(string) $value];
            }
            if (!is_bool($value) && !is_float($value) && !is_int($value) && !is_string($value)) {
                throw new \DomainException(sprintf('The Realisaprint mapping cannot resolve "%s".', $variable));
            }
            $variables[$variable] = $value;
        }

        return $variables;
    }
}
