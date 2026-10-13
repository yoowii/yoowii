<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

/** Updates a linked product draft and creates a new immutable supplier mapping version. */
final readonly class RealisaprintLinkedProductDefinitionEditor
{
    public function __construct(private EntityManagerInterface $entityManager, private RealisaprintMappingCompleteness $completeness)
    {
    }

    /** @param array<string, array<string, mixed>> $options @param list<string> $pricingAxes */
    public function update(SupplierRoute $route, RealisaprintCatalogProduct $catalog, PersistedPrintProductDefinition $definition, string $configuratorMode, array $options, array $pricingAxes): SupplierProductMappingVersion
    {
        $previous = $this->latestMapping($route);
        $this->assertDraft($definition, $previous);
        $provider = $previous->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider) || !is_scalar($provider['stock'] ?? null) || !is_array($catalog->configuration())) {
            throw new \DomainException('La configuration fournisseur synchronisée et le mapping du brouillon sont obligatoires.');
        }
        if (!in_array($configuratorMode, ['classic', 'prescript'], true)) {
            throw new \InvalidArgumentException('Le mode de configurateur Realisaprint est invalide.');
        }
        if ('prescript' === $configuratorMode && !$catalog->supportsPrescriptStock((string) $provider['stock'])) {
            throw new \DomainException('Le stock Realisaprint enregistré n’est pas disponible dans l’API Préscript.');
        }

        $definition->replaceDraftDefinition($options, $pricingAxes);
        $provider['configurator'] = $configuratorMode;
        $provider['variables'] = $this->rules($options, $catalog);
        $this->completeness->assertComplete($definition, $catalog->configuration(), $provider);

        $mapping = new SupplierProductMappingVersion($route->supplierProduct(), $route->yoowiiProductCode(), $this->nextVersion($route), ['realisaprint' => $provider], new \DateTimeImmutable('now', new \DateTimeZone('UTC')), null, $definition);
        $mapping->deactivate();
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return $mapping;
    }

    /** @param array<string, array<string, mixed>> $options @return array<string, array{option: string, values: array<string, mixed>, quantity?: true, fixed_value?: bool|float|int|string}> */
    private function rules(array $options, RealisaprintCatalogProduct $catalog): array
    {
        $catalogVariables = $catalog->configuration()['variables'] ?? [];
        $catalogVariables = is_array($catalogVariables) ? $catalogVariables : [];
        $rules = [];
        foreach ($options as $code => $option) {
            $variable = $option['provider_variable'] ?? null;
            if (!is_string($variable)) {
                continue;
            }
            $rules[$variable] = ['option' => $code, 'values' => is_array($option['provider_values'] ?? null) ? $option['provider_values'] : []];
            $catalogVariable = $catalogVariables[$variable] ?? null;
            if (is_array($catalogVariable) && true === ($catalogVariable['quantity'] ?? false)) {
                $rules[$variable]['quantity'] = true;
            }
            if (is_scalar($option['provider_fixed_value'] ?? null)) {
                $rules[$variable]['fixed_value'] = $option['provider_fixed_value'];
            }
        }

        return $rules;
    }

    private function assertDraft(PersistedPrintProductDefinition $definition, SupplierProductMappingVersion $mapping): void
    {
        if ($definition->active() || (null !== $mapping->printProductDefinition() && $mapping->printProductDefinition() !== $definition)) {
            throw new \DomainException('Seule la dernière révision inactive peut être modifiée.');
        }
    }

    private function latestMapping(SupplierRoute $route): SupplierProductMappingVersion
    {
        $mapping = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findOneBy(['supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode()], ['id' => 'DESC']);
        if (!$mapping instanceof SupplierProductMappingVersion) {
            throw new \DomainException('Aucun mapping du produit lié n’a été trouvé.');
        }

        return $mapping;
    }

    private function nextVersion(SupplierRoute $route): string
    {
        $max = 0;
        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['supplierProduct' => $route->supplierProduct(), 'yoowiiProductCode' => $route->yoowiiProductCode()]) as $mapping) {
            if (preg_match('/^v(\d+)$/D', $mapping->version(), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'v' . ($max + 1);
    }
}
