<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

/** Creates a disabled, non-routable Sylius print product from the read-only provider catalogue. */
final readonly class RealisaprintDraftCreator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, array{type: string, required?: bool, allowed_values?: list<string|int>, minimum?: int|null, maximum?: int|null}> $options
     * @param non-empty-list<string> $pricingAxes
     */
    public function create(RealisaprintCatalogProduct $catalogProduct, string $productCode, string $name, string $stock, array $options, array $pricingAxes): Product
    {
        if (null === $catalogProduct->configuration()) {
            throw new \DomainException('Charge d’abord la configuration Realisaprint avant de créer le brouillon.');
        }
        if (!$this->isValidPrintCode($productCode)) {
            throw new \InvalidArgumentException('Le code produit doit respecter le format PRINT_MAJUSCULES.');
        }
        if ('' === $stock) {
            throw new \InvalidArgumentException('Le stock Realisaprint est obligatoire.');
        }
        if (null !== $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $productCode])) {
            throw new \DomainException('Un produit Sylius utilise déjà ce code.');
        }
        if (null !== $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $productCode])) {
            throw new \DomainException('Une définition de configurateur utilise déjà ce code.');
        }

        // Build it now to reject malformed option definitions before anything is persisted.
        $definition = new PersistedPrintProductDefinition($productCode, 'v1', $options, $pricingAxes);
        $definition->definition();

        /** @var PrintSupplier|null $supplier */
        $supplier = $this->entityManager->getRepository(PrintSupplier::class)->findOneBy(['code' => 'realisaprint']);
        if (!$supplier instanceof PrintSupplier) {
            throw new \DomainException('Le fournisseur realisaprint doit être configuré avant la création du brouillon.');
        }

        $supplierProduct = $this->entityManager->getRepository(SupplierProduct::class)->findOneBy([
            'supplier' => $supplier,
            'code' => $catalogProduct->providerProductId(),
        ]);
        if (!$supplierProduct instanceof SupplierProduct) {
            $supplierProduct = new SupplierProduct($supplier, $catalogProduct->providerProductId(), $catalogProduct->name());
            $supplierProduct->deactivate();
            $this->entityManager->persist($supplierProduct);
        }

        $product = new Product();
        $product->setCode($productCode);
        $product->setCurrentLocale('fr_FR');
        $product->setFallbackLocale('fr_FR');
        $product->setName($name);
        $product->setEnabled(false);
        $product->setFulfillmentType(FulfillmentType::Print);
        $product->setPrintDefinitionCode($productCode);

        $variant = new ProductVariant();
        $variant->setCode($productCode . '_DEFAULT');
        $variant->setEnabled(false);
        $product->addVariant($variant);

        $route = new SupplierRoute($productCode, $supplierProduct, 1, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $route->deactivate();
        $variables = [];
        foreach ($options as $code => $option) {
            if (!is_string($code) || !is_array($option) || !is_string($option['provider_variable'] ?? null)) {
                continue;
            }
            $variables[$option['provider_variable']] = [
                'option' => $code,
                'values' => is_array($option['provider_values'] ?? null) ? $option['provider_values'] : [],
            ];
        }
        $mapping = new SupplierProductMappingVersion(
            $supplierProduct,
            $productCode,
            'v1',
            ['realisaprint' => ['product' => $catalogProduct->providerProductId(), 'stock' => $stock, 'variables' => $variables]],
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        );
        $mapping->deactivate();

        $this->entityManager->persist($definition);
        $this->entityManager->persist($product);
        $this->entityManager->persist($route);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return $product;
    }

    private function isValidPrintCode(string $productCode): bool
    {
        return 1 === preg_match('/^PRINT_[A-Z0-9_]+$/D', trim($productCode));
    }
}
