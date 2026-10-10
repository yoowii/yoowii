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
     * @param array<string, array<string, mixed>> $options
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
        $stock = trim($stock);
        $availableStocks = $this->availableStockIds($catalogProduct);
        if ([] === $availableStocks) {
            throw new \DomainException('Aucun stock Realisaprint n’est disponible. Synchronisez ou consultez la configuration Realisaprint avant de créer le brouillon.');
        }
        if ('' === $stock || !in_array($stock, $availableStocks, true)) {
            throw new \InvalidArgumentException('Le stock Realisaprint sélectionné n’appartient pas à la configuration synchronisée du produit fournisseur.');
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
        $product->setSlug(strtolower(str_replace('_', '-', $productCode)));
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
            if (!is_string($code) || !is_string($option['provider_variable'] ?? null)) {
                continue;
            }
            $rule = [
                'option' => $code,
                'values' => is_array($option['provider_values'] ?? null) ? $option['provider_values'] : [],
            ];
            if (true === ($catalogProduct->configuration()['variables'][$option['provider_variable']]['quantity'] ?? false)) {
                $rule['quantity'] = true;
            }
            if (is_string($option['provider_fixed_value'] ?? null)) {
                $rule['fixed_value'] = $option['provider_fixed_value'];
            }
            $variables[$option['provider_variable']] = $rule;
        }
        $configurationMapping = ['realisaprint' => ['product' => $catalogProduct->providerProductId(), 'stock' => $stock, 'variables' => $variables]];
        (new RealisaprintMappingCompleteness())->assertComplete($definition, $catalogProduct->configuration(), $configurationMapping['realisaprint']);
        $mapping = new SupplierProductMappingVersion(
            $supplierProduct,
            $productCode,
            'v1',
            $configurationMapping,
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

    /** @return list<string> */
    private function availableStockIds(RealisaprintCatalogProduct $catalogProduct): array
    {
        $stocks = $catalogProduct->configuration()['stocks'] ?? [];
        if (!is_array($stocks)) {
            return [];
        }

        $ids = [];
        foreach ($stocks as $id => $label) {
            if (is_scalar($label) && '' !== trim((string) $id)) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    private function isValidPrintCode(string $productCode): bool
    {
        return 1 === preg_match('/^PRINT_[A-Z0-9_]+$/D', trim($productCode));
    }
}
