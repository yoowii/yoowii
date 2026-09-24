<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;

/** Converts the active Print Sourcing mapping into Realisaprint API fields. */
final readonly class RealisaprintConfigurationMapper
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return array{product: string, stock: string, variables: array<string, scalar>} */
    public function map(PrintJob $job, \DateTimeImmutable $at): array
    {
        $configuration = $job->productionSnapshot()['pricing']['configuration'] ?? null;
        $yoowiiProductCode = is_array($configuration) ? ($configuration['product_code'] ?? null) : null;
        $options = is_array($configuration) ? ($configuration['options'] ?? null) : null;
        if (!is_string($yoowiiProductCode) || !is_array($options)) {
            throw new \DomainException('The production snapshot does not contain a valid print configuration.');
        }

        $mappings = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([
            'yoowiiProductCode' => $yoowiiProductCode,
            'active' => true,
        ]);
        foreach ($mappings as $mapping) {
            if (!$mapping instanceof SupplierProductMappingVersion ||
                $mapping->supplierProduct()->supplier()->code() !== $job->supplierCode() ||
                $mapping->supplierProduct()->code() !== $job->supplierProductCode() ||
                !$mapping->isEffectiveAt($at)) {
                continue;
            }
            $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
            if (!is_array($provider)) {
                continue;
            }
            $product = $provider['product'] ?? null;
            $stock = $provider['stock'] ?? null;
            $variables = $provider['variables'] ?? null;
            if (!is_scalar($product) || !is_scalar($stock) || !is_array($variables)) {
                throw new \DomainException('The active Realisaprint mapping must define product, stock and variables.');
            }

            return ['product' => (string) $product, 'stock' => (string) $stock, 'variables' => $this->variables($variables, $options, $job)];
        }

        throw new \DomainException('No active Realisaprint configuration mapping exists for this print job.');
    }

    /** @param array<string, mixed> $rules @param array<string, mixed> $options @return array<string, scalar> */
    private function variables(array $rules, array $options, PrintJob $job): array
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
            $value = 'quantity' === $rule['option'] ? $job->orderItem()->getQuantity() : ($options[$rule['option']] ?? null);
            $values = $rule['values'] ?? null;
            if (is_array($values) && null !== $value && array_key_exists((string) $value, $values)) {
                $value = $values[(string) $value];
            }
            if (!is_scalar($value)) {
                throw new \DomainException(sprintf('The Realisaprint mapping cannot resolve "%s".', $variable));
            }
            $variables[$variable] = $value;
        }

        return $variables;
    }
}
