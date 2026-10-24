<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Channel\Channel;
use App\Entity\Channel\ChannelPricing;
use App\Entity\Product\Product;
use App\Entity\Product\ProductTaxon;
use App\Entity\Product\ProductTranslation;
use App\Entity\Product\ProductVariant;
use App\Entity\Taxonomy\Taxon;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Infrastructure\Translation\ProductTranslationDictionary;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** Creates a disabled, non-routable Sylius print product from the read-only provider catalogue. */
final readonly class RealisaprintDraftCreator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RealisaprintDraftTaxonResolver $taxons,
        private ProductTranslationDictionary $translations,
        private LoggerInterface $logger,
        private ?RealisaprintDraftPictureImporter $pictureImporter = null,
    ) {
    }

    /**
     * @param array<string, array<string, mixed>> $options
     * @param non-empty-list<string> $pricingAxes
     */
    public function create(RealisaprintCatalogProduct $catalogProduct, string $productCode, string $name, string $stock, string $configuratorMode, array $options, array $pricingAxes, ?string $selectedPicture = null): Product
    {
        if (null === $catalogProduct->configuration()) {
            throw new \DomainException('Charge d’abord la configuration Realisaprint avant de créer le brouillon.');
        }
        $productCode = trim($productCode);
        if (!$this->isValidPrintCode($productCode)) {
            throw new \InvalidArgumentException('Le code produit doit respecter le format PRINT_MAJUSCULES.');
        }
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Le nom commercial ne peut pas être vide.');
        }
        $stock = trim($stock);
        $stocks = $this->stockNames($catalogProduct);
        if ([] === $stocks) {
            throw new \DomainException('Aucun stock Realisaprint n’est disponible. Synchronisez ou consultez la configuration Realisaprint avant de créer le brouillon.');
        }
        if ('' === $stock || !isset($stocks[$stock])) {
            throw new \InvalidArgumentException('Le stock Realisaprint sélectionné n’appartient pas à la configuration synchronisée du produit fournisseur.');
        }
        $stockName = $stocks[$stock];
        if (!in_array($configuratorMode, ['classic', 'prescript'], true)) {
            throw new \InvalidArgumentException('Le mode de configurateur Realisaprint est invalide.');
        }
        if ('prescript' === $configuratorMode && !$catalogProduct->supportsPrescriptStock($stock)) {
            throw new \DomainException('Le stock Realisaprint sélectionné n’est pas disponible dans l’API Préscript.');
        }
        if (null !== $this->entityManager->getRepository(Product::class)->findOneBy(['code' => $productCode])) {
            throw new \DomainException('Un produit Sylius utilise déjà ce code.');
        }
        // A configurator belongs to the supplier catalogue product, not to one
        // of its stocks. Several Yoowii products can therefore share it while
        // retaining one stock-specific supplier mapping each.
        $definitionCode = RealisaprintPrintDefinitionCode::fromProviderProductId($catalogProduct->providerProductId());
        $candidateDefinition = new PersistedPrintProductDefinition($definitionCode, 'v1', $this->definitionOptions($options), $pricingAxes);
        $candidateDefinition->replaceDraftDefinition($options, $pricingAxes);
        /** @var PersistedPrintProductDefinition|null $definition */
        $definition = $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $definitionCode], ['id' => 'DESC']);
        if ($definition instanceof PersistedPrintProductDefinition) {
            $this->assertCompatibleDefinition($definition, $candidateDefinition);
        } else {
            $definition = $candidateDefinition;
        }

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
        $product->setFallbackLocale('fr_FR');
        $this->translate($product, 'fr_FR', $name);
        $this->translateEnglish($product, $name, $stockName);
        $product->setCurrentLocale('fr_FR');
        $product->setEnabled(false);
        $product->setFulfillmentType(FulfillmentType::Print);
        // The product must resolve the exact definition created above.  A
        // supplier catalogue label is not a stable identity: several Yoowii
        // products may be derived from the same supplier product.
        $product->setPrintDefinitionCode($definition->productCode());

        $variant = new ProductVariant();
        $variant->setCode($productCode . '_DEFAULT');
        $variant->setEnabled(false);
        $this->initializeChannelPricings($product, $variant);
        $product->addVariant($variant);

        $taxon = $this->taxons->resolve($name, $catalogProduct->name());
        if ($taxon instanceof Taxon) {
            $productTaxon = new ProductTaxon();
            $productTaxon->setTaxon($taxon);
            $product->addProductTaxon($productTaxon);
            $product->setMainTaxon($taxon);
        }

        $route = new SupplierRoute($productCode, $supplierProduct, 1, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $route->deactivate();
        $variables = [];
        $providerVariables = $this->providerVariables($catalogProduct);
        foreach ($options as $code => $option) {
            $providerVariable = $option['provider_variable'] ?? null;
            if (!is_string($providerVariable)) {
                continue;
            }
            $rule = [
                'option' => $code,
                'values' => is_array($option['provider_values'] ?? null) ? $option['provider_values'] : [],
            ];
            if (true === ($providerVariables[$providerVariable]['quantity'] ?? false)) {
                $rule['quantity'] = true;
            }
            if (is_string($option['provider_fixed_value'] ?? null)) {
                $rule['fixed_value'] = $option['provider_fixed_value'];
            }
            $variables[$providerVariable] = $rule;
        }
        $providerMapping = ['product' => $catalogProduct->providerProductId(), 'stock' => $stock, 'configurator' => $configuratorMode];
        if ('classic' === $configuratorMode) {
            $providerMapping['variables'] = $variables;
            (new RealisaprintMappingCompleteness())->assertComplete($definition, $catalogProduct->configuration(), $providerMapping);
        }
        $configurationMapping = [
            'realisaprint' => $providerMapping,
            // The definition is linked to this mapping below; declaring the
            // driver here makes the generated draft ready for the generic
            // storefront resolver instead of coupling the product itself to
            // Realisaprint.
            'yoowii' => ['configurator_driver' => 'prescript' === $configuratorMode ? 'realisaprint_prescript' : 'realisaprint_classic'],
        ];
        $mapping = new SupplierProductMappingVersion(
            $supplierProduct,
            $productCode,
            'v1',
            $configurationMapping,
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            null,
            $definition,
        );
        $mapping->deactivate();

        if ($definition === $candidateDefinition) {
            $this->entityManager->persist($definition);
        }
        if (is_string($selectedPicture) && '' !== trim($selectedPicture)) {
            if (!$this->pictureImporter instanceof RealisaprintDraftPictureImporter) {
                throw new \LogicException('Le service d’import des images Realisaprint est indisponible.');
            }
            $this->pictureImporter->attach($product, $selectedPicture);
        }
        $this->entityManager->persist($product);
        $this->entityManager->persist($route);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return $product;
    }

    /** @return array<string, string> */
    private function stockNames(RealisaprintCatalogProduct $catalogProduct): array
    {
        $stocks = $catalogProduct->configuration()['stocks'] ?? [];
        if (!is_array($stocks)) {
            return [];
        }

        $stockNames = [];
        foreach ($stocks as $id => $label) {
            if (is_scalar($label) && '' !== trim((string) $id)) {
                $stockNames[(string) $id] = (string) $label;
            }
        }

        return $stockNames;
    }

    private function isValidPrintCode(string $productCode): bool
    {
        return 1 === preg_match('/^PRINT_[A-Z0-9_]+$/D', $productCode);
    }

    private function assertCompatibleDefinition(PersistedPrintProductDefinition $existing, PersistedPrintProductDefinition $candidate): void
    {
        if ($existing->options() !== $candidate->options() || $existing->pricingAxes() !== $candidate->pricingAxes()) {
            throw new \DomainException('La définition de configurateur partagée ne correspond pas aux options sélectionnées. Créez une révision avant de modifier son schéma.');
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function providerVariables(RealisaprintCatalogProduct $catalogProduct): array
    {
        $variables = $catalogProduct->configuration()['variables'] ?? [];
        if (!is_array($variables)) {
            return [];
        }

        $providerVariables = [];
        foreach ($variables as $code => $variable) {
            if (is_string($code) && is_array($variable)) {
                $providerVariables[$code] = $variable;
            }
        }

        return $providerVariables;
    }

    /**
     * @param array<string, array<string, mixed>> $options
     * @return array<string, array{type: string, required?: bool, allowed_values?: list<string|int|float>, minimum?: int|float|null, maximum?: int|float|null}>
     */
    private function definitionOptions(array $options): array
    {
        $definitionOptions = [];
        foreach ($options as $code => $option) {
            $type = $option['type'] ?? null;
            if (!is_string($type)) {
                throw new \DomainException(sprintf('Le type de l’option "%s" est invalide.', $code));
            }

            $definitionOption = ['type' => $type];
            if (is_bool($option['required'] ?? null)) {
                $definitionOption['required'] = $option['required'];
            }
            if (is_array($option['allowed_values'] ?? null)) {
                $allowedValues = [];
                foreach ($option['allowed_values'] as $value) {
                    if (is_string($value) || is_int($value) || is_float($value)) {
                        $allowedValues[] = $value;
                    }
                }
                $definitionOption['allowed_values'] = $allowedValues;
            }
            foreach (['minimum', 'maximum'] as $bound) {
                if (is_int($option[$bound] ?? null) || is_float($option[$bound] ?? null)) {
                    $definitionOption[$bound] = $option[$bound];
                }
            }
            $definitionOptions[$code] = $definitionOption;
        }

        return $definitionOptions;
    }

    private function translate(Product $product, string $locale, string $name): void
    {
        $translation = new ProductTranslation();
        $translation->setLocale($locale);
        $translation->setName($name);
        $translation->setSlug((new AsciiSlugger())->slug($name)->lower()->toString());
        $product->addTranslation($translation);
    }

    private function translateEnglish(Product $product, string $name, string $stockName): void
    {
        if ($product->getTranslations()->containsKey('en_US')) {
            return;
        }

        $translation = $name === $stockName
            ? $this->translations->stock($stockName)
            : $this->translations->product($name);
        if (null === $translation) {
            $this->logger->warning('Missing English product draft translation.', [
                'label' => $name,
                'source' => $name === $stockName ? 'stock' : 'product',
            ]);
            // Preserve the previous draft-creation behavior until the local
            // dictionary is deliberately extended.
            $translation = $name;
        }

        $this->translate($product, 'en_US', $translation);
    }

    private function initializeChannelPricings(Product $product, ProductVariant $variant): void
    {
        foreach ($this->entityManager->getRepository(Channel::class)->findAll() as $channel) {
            if (null === $channel->getCode()) {
                continue;
            }
            $product->addChannel($channel);
            $pricing = new ChannelPricing();
            $pricing->setChannelCode($channel->getCode());
            $pricing->setPrice(100);
            $pricing->setOriginalPrice(100);
            $pricing->setMinimumPrice(100);
            $variant->addChannelPricing($pricing);
        }
    }
}
