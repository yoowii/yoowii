<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RealisaprintPublicationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function publish(SupplierProductMappingVersion $mapping, SupplierRoute $route, \DateTimeImmutable $now): void
    {
        /** @var RealisaprintMappingValidation|null $validation */
        $validation = $this->entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']);
        if (!$validation instanceof RealisaprintMappingValidation || !$validation->coverageComplete() || !$validation->quotePassed()) {
            throw new \DomainException('Publication refusée : une validation de couverture et une cotation API réussies sont requises.');
        }
        if ($validation->checkedAt() < $now->modify('-30 minutes')) {
            throw new \DomainException('Publication refusée : la validation API a expiré, relance-la.');
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
