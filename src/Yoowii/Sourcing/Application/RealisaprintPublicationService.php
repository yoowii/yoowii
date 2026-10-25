<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintPublicationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RealisaprintMappingCompleteness $completeness,
        private RealisaprintPrescriptPublicationPolicy $prescriptPolicy,
        private bool $prescriptQuickPublicationEnabled,
    ) {
    }

    public function publish(SupplierProductMappingVersion $mapping, SupplierRoute $route, \DateTimeImmutable $now): void
    {
        /** @var RealisaprintMappingValidation|null $validation */
        $validation = $this->entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']);
        if (!$validation instanceof RealisaprintMappingValidation || !$validation->coverageComplete() || !$validation->quotePassed()) {
            throw new \DomainException('Publication refusée : une validation de couverture et une cotation API réussies sont requises.');
        }
        if ($validation->testFingerprint() !== hash('sha256', json_encode(['mapping' => $mapping->configurationMapping(), 'sample' => $validation->testConfiguration()], \JSON_THROW_ON_ERROR))) {
            throw new \DomainException('Publication refusée : la configuration d’essai ou le mapping a changé, relancez le contrôle.');
        }
        if ($validation->checkedAt() < $now->modify('-30 minutes')) {
            throw new \DomainException('Publication refusée : la validation API a expiré, relance-la.');
        }
        /** @var Product|null $product */
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $mapping->yoowiiProductCode()]);
        $definition = $mapping->printProductDefinition();
        if (!$product instanceof Product || !$definition instanceof PersistedPrintProductDefinition) {
            throw new \DomainException('Le produit Sylius ou sa définition de configurateur est introuvable.');
        }
        $this->assertCanonicalDefinition($product, $definition, $route);
        if ($route->supplierProduct() !== $mapping->supplierProduct() || $route->yoowiiProductCode() !== $mapping->yoowiiProductCode()) {
            throw new \DomainException('La route ne correspond pas au mapping à publier.');
        }

        $catalog = $this->entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);
        if (!$catalog instanceof RealisaprintCatalogProduct || !is_array($catalog->configuration())) {
            throw new \DomainException('Publication refusée : configuration fournisseur synchronisée introuvable.');
        }
        $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
        if (!is_array($provider)) {
            throw new \DomainException('Publication refusée : correspondances fournisseur introuvables.');
        }
        $providerMapping = [];
        foreach ($provider as $key => $value) {
            if (!is_string($key)) {
                throw new \DomainException('Publication refusée : correspondances fournisseur invalides.');
            }
            $providerMapping[$key] = $value;
        }
        $this->completeness->assertComplete($definition, $catalog->configuration(), $providerMapping);
        $this->activate($mapping, $route, $product, $definition, $now, true, true);
    }

    public function prescriptQuickPublicationEnabled(): bool
    {
        return $this->prescriptQuickPublicationEnabled;
    }

    /**
     * Publishes an explicitly selected Prescript mapping without exercising the
     * native API configurator validation path. Runtime Prescript quotations keep
     * their own server-side get_price boundary.
     */
    public function publishPrescript(SupplierProductMappingVersion $mapping, SupplierRoute $route, \DateTimeImmutable $now): void
    {
        if (!$this->prescriptQuickPublicationEnabled) {
            throw new \DomainException('La publication rapide Préscript est désactivée par le feature flag.');
        }

        /** @var Product|null $product */
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $mapping->yoowiiProductCode()]);
        $definition = $mapping->printProductDefinition();
        if (!$product instanceof Product || !$definition instanceof PersistedPrintProductDefinition) {
            throw new \DomainException('Le produit Sylius ou sa définition de configurateur est introuvable.');
        }
        $this->assertCanonicalDefinition($product, $definition, $route);
        $catalog = $this->entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);
        if (!$catalog instanceof RealisaprintCatalogProduct) {
            throw new \DomainException('Publication Préscript refusée : produit fournisseur synchronisé introuvable.');
        }
        $this->prescriptPolicy->assertPublishable($mapping, $route, $catalog);
        // Préscript replaces the currently published classic mapping for this
        // commercial product. Keeping both active would make the storefront
        // route ambiguous and would leave the previous driver selectable.
        // Préscript renders and quotes the supplier document directly. Its
        // mapping may therefore reference a draft definition without making
        // that shared definition active for other classic products.
        $this->activate($mapping, $route, $product, $definition, $now, true, false);
    }

    private function activate(SupplierProductMappingVersion $mapping, SupplierRoute $route, Product $product, PersistedPrintProductDefinition $definition, \DateTimeImmutable $now, bool $replaceActiveVersions, bool $activateDefinition): void
    {
        if ($replaceActiveVersions) {
            foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([
                'supplierProduct' => $route->supplierProduct(),
                'yoowiiProductCode' => $mapping->yoowiiProductCode(),
                'active' => true,
            ]) as $previous) {
                if ($previous !== $mapping) {
                    $previous->deactivate();
                }
            }
            foreach ($this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findBy([
                'productCode' => $mapping->yoowiiProductCode(),
                'active' => true,
            ]) as $previousDefinition) {
                if ($previousDefinition !== $definition) {
                    $previousDefinition->deactivate();
                }
            }
        }

        $route->supplierProduct()->activate();
        $mapping->markPublished($now);
        $route->activate();
        if ($activateDefinition) {
            $definition->activate();
        }
        $product->setEnabled(true);
        foreach ($product->getVariants() as $variant) {
            $variant->setEnabled(true);
        }
        $this->entityManager->flush();
    }

    private function assertCanonicalDefinition(Product $product, PersistedPrintProductDefinition $definition, SupplierRoute $route): void
    {
        $expected = RealisaprintPrintDefinitionCode::fromProviderProductId($route->supplierProduct()->code());
        if ($definition->productCode() !== $expected || $product->getPrintDefinitionCode() !== $expected) {
            throw new \DomainException('Publication refusée : les produits issus du catalogue Realisaprint doivent utiliser la définition de configurateur partagée de leur produit fournisseur.');
        }
    }
}
