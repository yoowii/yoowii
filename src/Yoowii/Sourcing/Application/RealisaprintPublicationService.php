<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintPublicationService
{
    public function __construct(private EntityManagerInterface $entityManager, private RealisaprintMappingCompleteness $completeness)
    {
    }

    public function publish(SupplierProductMappingVersion $mapping, SupplierRoute $route, \DateTimeImmutable $now): void
    {
        /** @var RealisaprintMappingValidation|null $validation */
        $validation = $this->entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']);
        if (!$validation instanceof RealisaprintMappingValidation || !$validation->coverageComplete() || !$validation->quotePassed()) {
            throw new \DomainException('Publication refusée : une validation de couverture et une cotation API réussies sont requises.');
        }
        if ($validation->testFingerprint() !== hash('sha256', json_encode(['mapping' => $mapping->configurationMapping(), 'sample' => $validation->testConfiguration()], JSON_THROW_ON_ERROR))) {
            throw new \DomainException('Publication refusée : la configuration d’essai ou le mapping a changé, relancez le contrôle.');
        }
        if ($validation->checkedAt() < $now->modify('-30 minutes')) {
            throw new \DomainException('Publication refusée : la validation API a expiré, relance-la.');
        }
        $initialState = $validation->initialConfiguratorState();
        if (!is_array($initialState) || ($initialState['fingerprint'] ?? null) !== $validation->testFingerprint() || ($initialState['mapping_version'] ?? null) !== $mapping->version()) {
            throw new \DomainException('Publication refusée : l’état initial du configurateur est absent ou périmé, relance le contrôle.');
        }
        /** @var Product|null $product */
        $product = $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $mapping->yoowiiProductCode()]);
        /** @var PersistedPrintProductDefinition|null $definition */
        $definition = $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $mapping->yoowiiProductCode()]);
        if (!$product instanceof Product || !$definition instanceof PersistedPrintProductDefinition) {
            throw new \DomainException('Le produit Sylius ou sa définition de configurateur est introuvable.');
        }
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
        $this->completeness->assertComplete($definition, $catalog->configuration(), $provider);
        if (($initialState['stock'] ?? null) !== ($provider['stock'] ?? null) || ($initialState['schema_version'] ?? null) !== $definition->definition()->schemaVersion()) {
            throw new \DomainException('Publication refusée : la définition ou le stock ont changé, relance le contrôle.');
        }

        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([
            'supplierProduct' => $route->supplierProduct(),
            'yoowiiProductCode' => $mapping->yoowiiProductCode(),
            'active' => true,
        ]) as $previous) {
            if ($previous !== $mapping) {
                $previous->deactivate();
            }
        }

        $route->supplierProduct()->activate();
        $mapping->activate();
        $route->activate();
        $definition->activate();
        $product->setEnabled(true);
        foreach ($product->getVariants() as $variant) {
            $variant->setEnabled(true);
        }
        $this->entityManager->flush();
    }
}
