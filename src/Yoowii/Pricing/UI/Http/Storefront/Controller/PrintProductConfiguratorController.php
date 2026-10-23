<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Pricing\Application\PrintConfigurationCatalog;
use App\Yoowii\Pricing\Application\PrintQuoteService;
use App\Yoowii\Pricing\Application\PublishedConfiguratorValues;
use App\Yoowii\Pricing\Application\Quote\PrintQuoteStore;
use App\Yoowii\Pricing\Application\Quote\StoredPrintQuote;
use App\Yoowii\Pricing\Application\RealisaprintAvailabilityFallback;
use App\Yoowii\Pricing\Application\RealisaprintConfiguratorRefresh;
use App\Yoowii\Pricing\Application\RealisaprintFixedOptionResolver;
use App\Yoowii\Pricing\Application\RealisaprintPrescriptTemplateProvider;
use App\Yoowii\Pricing\Application\RealisaprintVisibleConfigurationBuilder;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\Application\StorefrontInitialConfiguratorValues;
use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
use App\Yoowii\Pricing\Domain\Print\PrescriptSummaryProfile;
use App\Yoowii\Pricing\UI\Http\Storefront\Form\PrintConfiguratorType;
use App\Yoowii\Sourcing\Application\RealisaprintInitialDisplayStateBuilder;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Psr\Log\LoggerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PrintProductConfiguratorController extends AbstractController
{
    /** @param ProductRepositoryInterface<Product> $productRepository */
    #[Route('/products/{productCode}/print-configuration/refresh', name: 'yoowii_shop_print_product_configuration_refresh', requirements: ['productCode' => '[A-Za-z0-9._-]+'], methods: ['POST'])]
    public function refresh(
        string $productCode,
        Request $request,
        ProductRepositoryInterface $productRepository,
        BuiltInPrintProductDefinitionRegistry $definitions,
        RealisaprintConfiguratorRefresh $refresh,
        ChannelContextInterface $channelContext,
        RealisaprintFixedOptionResolver $fixedOptions,
        PublishedConfiguratorValues $publishedValues,
        RealisaprintAvailabilityFallback $availabilityFallback,
        StorefrontPrintSourcingResolver $sourcingResolver,
    ): Response {
        $product = $this->findPrintProduct($productCode, $productRepository, $channelContext);

        try {
            $payload = $request->toArray();
            $values = $payload['options'] ?? null;
            if (!is_array($values)) {
                throw new \InvalidArgumentException('La configuration à rafraîchir est invalide.');
            }
            $definition = $definitions->get($this->definitionCode($product));
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $sourcing = $sourcingResolver->resolve($productCode, $now);
            $mapping = $sourcing->mapping();
            if (ConfiguratorKind::SupplierApi !== $sourcing->configuratorKind() || !$mapping instanceof SupplierProductMappingVersion) {
                throw new \DomainException('Le configurateur sélectionné ne prend pas en charge ce rafraîchissement.');
            }
            $configuration = $definition->configure($publishedValues->resolve($definition, $definitions->storefrontSchema($this->definitionCode($product)), $values, $fixedOptions->forMapping($mapping)));

            // show_variables is a display endpoint. It may run before a
            // configuration is priceable; quote() remains the strict boundary.
            $selectedOptions = array_filter($values, static fn (mixed $value): bool => is_scalar($value) && '' !== trim((string) $value));
            $state = $refresh->refreshForMapping($configuration, $mapping, $selectedOptions);
            $corrected = $availabilityFallback->applyToSchema($configuration->toArray(), $state, $definitions->storefrontSchema($this->definitionCode($product)), $definition->pricingAxes());
            foreach ($corrected as $option => $value) {
                if ((string) ($configuration->toArray()[$option] ?? '') !== (string) $value) {
                    $state['current'][$option] = (string) $value;
                }
            }

            return new JsonResponse($state, Response::HTTP_OK, ['Cache-Control' => 'no-store']);
        } catch (\InvalidArgumentException|\DomainException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        } catch (\Throwable) {
            return new JsonResponse(['message' => 'Les options ne peuvent pas être mises à jour pour le moment.'], Response::HTTP_UNPROCESSABLE_ENTITY, ['Cache-Control' => 'no-store']);
        }
    }

    /** @param ProductRepositoryInterface<Product> $productRepository */
    #[Route('/products/{productCode}/print-quote', name: 'yoowii_shop_print_product_quote', requirements: ['productCode' => '[A-Za-z0-9._-]+'], methods: ['POST'])]
    public function quote(
        string $productCode,
        Request $request,
        ProductRepositoryInterface $productRepository,
        BuiltInPrintProductDefinitionRegistry $definitions,
        PrintConfigurationCatalog $configurationCatalog,
        PrintQuoteService $quoteService,
        RetailPrintPricingPolicyProvider $pricingPolicyProvider,
        PrintQuoteStore $quoteStore,
        CurrencyContextInterface $currencyContext,
        ChannelContextInterface $channelContext,
        RealisaprintFixedOptionResolver $fixedOptions,
        PublishedConfiguratorValues $publishedValues,
        RealisaprintConfiguratorRefresh $refresh,
        RealisaprintVisibleConfigurationBuilder $visibleConfigurationBuilder,
        StorefrontPrintSourcingResolver $sourcingResolver,
    ): Response {
        $product = $this->findPrintProduct($productCode, $productRepository, $channelContext);
        $definitionCode = $this->definitionCode($product);
        $definition = $definitions->get($definitionCode);
        $currencyCode = $currencyContext->getCurrencyCode();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sourcing = $sourcingResolver->resolve($productCode, $now);
        $availableOptions = $configurationCatalog->availableOptions($definition, $currencyCode, $now, $sourcing->route());
        $fixed = $fixedOptions->forMapping($sourcing->mapping());
        $schemas = $this->withFixedSchema($definitions->storefrontSchema($definitionCode), $fixed);
        $submitted = $request->request->all('print_configurator');
        $form = $this->createConfiguratorForm($productCode, $availableOptions, $request, [], $schemas);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->quoteError(
                $request,
                $product,
                'La configuration sélectionnée est invalide.',
            );
        }

        try {
            $formData = $form->getData();

            if (!is_array($formData)) {
                throw new \InvalidArgumentException('La configuration reçue est invalide.');
            }

            $values = $publishedValues->resolve($definition, $schemas, $formData, $fixed);
            /** @var array<string, mixed> $values */
            $configuration = $definition->configure($values);
            if (ConfiguratorKind::SupplierApi === $sourcing->configuratorKind()) {
                $mapping = $sourcing->mapping();
                if (!$mapping instanceof SupplierProductMappingVersion) {
                    throw new \LogicException('Le sourcing API résolu ne possède pas de mapping publié.');
                }
                $state = $refresh->refreshForMapping($configuration, $mapping);
                $this->assertCustomerPricingAxesComplete($formData, $definition->pricingAxes(), $fixed, $state);
                $configuration = $visibleConfigurationBuilder->buildVisibleProviderConfiguration($configuration, $state);
            } else {
                $this->assertPricingAxesComplete($configuration, $definition->pricingAxes());
            }
            $quote = $quoteService->quoteForRoute(
                $configuration,
                $pricingPolicyProvider->get(),
                $currencyCode,
                $now,
                $sourcing->route(),
            );
            $variant = $this->commercialVariant($product);
            $variantCode = $variant->getCode();

            if (null === $variantCode) {
                throw new \LogicException('The print product variant must have a code.');
            }

            $quoteToken = $quoteStore->issue(
                $variantCode,
                $definitionCode,
                $quote->pricingSnapshot(),
                $now,
            );
        } catch (\InvalidArgumentException|\DomainException $exception) {
            return $this->quoteError($request, $product, $exception->getMessage());
        }

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'quote_token' => $quoteToken,
                'quote_html' => $this->renderView('shop/product/show/_print_quote.html.twig', [
                    'pricing_snapshot' => $quote->pricingSnapshot(),
                    'quote_token' => $quoteToken,
                ]),
            ], Response::HTTP_CREATED, ['Cache-Control' => 'no-store']);
        }

        return $this->redirectToProduct($product, $request, $quoteToken);
    }

    /** @param ProductRepositoryInterface<Product> $productRepository */
    public function component(
        string $productCode,
        ?string $quoteToken,
        Request $request,
        ProductRepositoryInterface $productRepository,
        BuiltInPrintProductDefinitionRegistry $definitions,
        PrintConfigurationCatalog $configurationCatalog,
        PrintQuoteStore $quoteStore,
        CurrencyContextInterface $currencyContext,
        ChannelContextInterface $channelContext,
        RealisaprintFixedOptionResolver $fixedOptions,
        PublishedConfiguratorValues $publishedValues,
        RealisaprintInitialDisplayStateBuilder $displayStateBuilder,
        StorefrontInitialConfiguratorValues $initialValues,
        KernelInterface $kernel,
        LoggerInterface $logger,
        RealisaprintPrescriptTemplateProvider $prescriptTemplates,
        UriSigner $uriSigner,
        StorefrontPrintSourcingResolver $sourcingResolver,
        bool $embeddedProductPage = false,
        ?string $configurationContext = null,
    ): Response {
        $product = $this->findPrintProduct($productCode, $productRepository, $channelContext);
        $definitionCode = $this->definitionCode($product);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $sourcing = $sourcingResolver->resolve($productCode, $now);
        $prescriptMapping = ConfiguratorKind::Prescript === $sourcing->configuratorKind()
            ? $sourcing->mapping()
            : null;
        if ($prescriptMapping instanceof SupplierProductMappingVersion) {
            $provider = $prescriptMapping->configurationMapping()['realisaprint'];
            $summaryProfile = PrescriptSummaryProfile::tryFromConfigurationMapping($prescriptMapping->configurationMapping());
            $callbackUrl = $uriSigner->sign($this->generateUrl('yoowii_shop_realisaprint_prescript_save_configuration', [
                'productCode' => $productCode,
                '_locale' => $request->getLocale(),
            ], UrlGeneratorInterface::ABSOLUTE_URL));

            try {
                $template = $prescriptTemplates->template((string) $provider['product'], (string) $provider['stock'], 1.0, 'FR', $callbackUrl);
            } catch (\Throwable) {
                return $this->render('shop/product/show/realisaprint_prescript_configurator.html.twig', [
                    'product' => $product,
                    'prescript_template' => '<div class="alert alert-warning" role="alert">Le configurateur fournisseur est momentanément indisponible.</div>',
                    'embedded_product_page' => $embeddedProductPage,
                    'configuration_context' => $configurationContext ?? 'print-' . $productCode,
                    'prescript_summary_profile' => $summaryProfile?->toArray(),
                ]);
            }

            return $this->render('shop/product/show/realisaprint_prescript_configurator.html.twig', [
                'product' => $product,
                'prescript_template' => $template,
                'embedded_product_page' => $embeddedProductPage,
                'configuration_context' => $configurationContext ?? 'print-' . $productCode,
                'prescript_summary_profile' => $summaryProfile?->toArray(),
            ]);
        }
        $definition = $definitions->get($definitionCode);
        $availableOptions = $configurationCatalog->availableOptions(
            $definition,
            $currencyContext->getCurrencyCode(),
            $now,
            $sourcing->route(),
        );
        $isRealisaprintClassic = ConfiguratorKind::SupplierApi === $sourcing->configuratorKind();
        $matrixCombinations = $isRealisaprintClassic
            ? []
            : $configurationCatalog->matrixCombinations($definition, $currencyContext->getCurrencyCode(), $now, $sourcing->route());
        $storedQuote = $this->matchingQuote($quoteToken, $product, $definitionCode, $quoteStore, $now);
        $fixed = $fixedOptions->forMapping($sourcing->mapping());
        $configuration = $storedQuote?->pricingSnapshot()->configuration()['options'] ?? [];
        $schemas = $this->withFixedSchema($definitions->storefrontSchema($definitionCode), $fixed);
        $initialSnapshot = $this->initialConfiguratorState($definitionCode, $sourcing->mapping(), $now, $displayStateBuilder, $kernel, $logger);
        $initialState = $initialSnapshot['state'];
        $formData = is_array($configuration) && [] !== $configuration
            ? $configuration
            : $initialValues->resolve($definition, $schemas, $fixed, $initialState);
        $form = $this->createConfiguratorForm(
            $productCode,
            $availableOptions,
            $request,
            $formData,
            $schemas,
        );

        return $this->render('shop/product/show/print_configurator.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'available' => $this->hasAvailableConfiguration($definition->pricingAxes(), $availableOptions, $this->withFixedSchema($definitions->storefrontSchema($definitionCode), $fixed)),
            'stored_quote' => $storedQuote,
            'quote_token' => null !== $storedQuote ? $quoteToken : null,
            'configuration' => is_array($configuration) ? $configuration : [],
            // Matrix pricing resolves compatibility locally from its published
            // rows. Only the Realisaprint controller may call show_variables.
            'refresh_url' => $isRealisaprintClassic
                ? $this->generateUrl('yoowii_shop_print_product_configuration_refresh', ['productCode' => $productCode, '_locale' => $request->getLocale()])
                : '',
            'matrix_combinations' => $matrixCombinations,
            'fixed_fields' => $fixed,
            'pricing_axes' => $definition->pricingAxes(),
            'initial_provider_state' => $initialState,
            'initial_provider_state_stale' => $initialSnapshot['stale'],
            'manual_quote' => $isRealisaprintClassic,
            'configurator_mode' => $isRealisaprintClassic ? 'realisaprint-classic' : 'matrix-print',
            'resolved_sourcing' => $sourcing,
            'embedded_product_page' => $embeddedProductPage,
            'configuration_context' => $configurationContext ?? 'print-' . $productCode,
        ]);
    }

    /**
     * @param array<string, list<string|int>> $availableOptions
     * @param array<string, mixed> $data
     * @param list<array{code: string, label: string, type: string, values: array<string, string>, area: int, position: int, readonly: bool, default: string|int|null}> $fieldSchemas
     */
    private function createConfiguratorForm(
        string $productCode,
        array $availableOptions,
        Request $request,
        array $data = [],
        array $fieldSchemas = [],
    ): \Symfony\Component\Form\FormInterface {
        return $this->createForm(PrintConfiguratorType::class, $data, [
            'action' => $this->generateUrl('yoowii_shop_print_product_quote', [
                'productCode' => $productCode,
                '_locale' => $request->getLocale(),
            ]),
            'method' => 'POST',
            'option_choices' => $availableOptions,
            'field_schemas' => $fieldSchemas,
            'product_code' => $productCode,
        ]);
    }

    /** @param array<string, mixed> $values @param array<string, array{value: string|int|float, label: string}> $fixed */
    private function withFixedValues(array $values, array $fixed): array
    {
        foreach ($fixed as $code => $field) {
            $values[$code] = $field['value'];
        }

        return $values;
    }

    /** @param list<array<string, mixed>> $schemas @param array<string, array{value: string|int|float, label: string}> $fixed
     * @return list<array<string, mixed>> */
    private function withFixedSchema(array $schemas, array $fixed): array
    {
        foreach ($schemas as &$schema) {
            if (isset($fixed[$schema['code'] ?? ''])) {
                $schema['fixed'] = true;
                $schema['fixed_label'] = $fixed[$schema['code']]['label'];
            }
        }
        unset($schema);

        return $schemas;
    }

    /** @return array{state: array<string, mixed>|null, stale: bool} */
    private function initialConfiguratorState(string $definitionCode, ?SupplierProductMappingVersion $mapping, \DateTimeImmutable $now, RealisaprintInitialDisplayStateBuilder $displayStateBuilder, KernelInterface $kernel, LoggerInterface $logger): array
    {
        if (!$mapping instanceof SupplierProductMappingVersion || 'realisaprint' !== $mapping->supplierProduct()->supplier()->code()) {
            return ['state' => null, 'stale' => false];
        }
        $definition = $mapping->printProductDefinition();
        if (!$definition instanceof \App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition) {
            return ['state' => null, 'stale' => false];
        }
            $state = $mapping->initialDisplayState();
            if (!is_array($state)) {
                $logger->warning('Realisaprint initial display state was rejected for the storefront.', [
                    'definition_code' => $definitionCode,
                    'mapping_id' => $mapping->id(),
                    'reason' => 'state_not_array',
                ]);
                if ('dev' === $kernel->getEnvironment()) {
                    $logger->debug('Realisaprint initial display state diagnostic.', [
                        'definition_code' => $definitionCode,
                        'mapping_id' => $mapping->id(),
                        'raw_initial_display_state' => $state,
                        'checks' => ['json_shape' => false, 'reason' => 'state_not_array'],
                        'initial_provider_state' => null,
                        'initial_provider_state_stale' => false,
                    ]);
                }

                return ['state' => null, 'stale' => false];
            }

            $compatibilityReason = $displayStateBuilder->compatibilityReason($state, $mapping, $definition);
            $freshnessReason = $displayStateBuilder->freshnessReason($state, $now);
            $compatible = null === $compatibilityReason;
            $fresh = null === $freshnessReason;
            if ('dev' === $kernel->getEnvironment()) {
                $checks = $displayStateBuilder->compatibilityChecks($state, $mapping, $definition);
                $logger->debug('Realisaprint initial display state diagnostic.', [
                    'definition_code' => $definitionCode,
                    'mapping_id' => $mapping->id(),
                    'raw_initial_display_state' => $state,
                    'checks' => [
                        ...$checks,
                        'expiration' => $fresh,
                        'compatibility_reason' => $compatibilityReason,
                        'freshness_reason' => $freshnessReason,
                    ],
                    'initial_provider_state' => $compatible ? $state : null,
                    'initial_provider_state_stale' => $compatible && !$fresh,
                ]);
            }
            if (!$compatible) {
                $logger->warning('Realisaprint initial display state was rejected for the storefront.', [
                    'definition_code' => $definitionCode,
                    'mapping_id' => $mapping->id(),
                    'reason' => $compatibilityReason,
                ]);
            }
            if ($compatible) {
                return ['state' => $state, 'stale' => !$fresh];
            }

        return ['state' => null, 'stale' => false];
    }

    /** @param list<string> $axes */
    private function assertPricingAxesComplete(\App\Yoowii\Pricing\Domain\Print\PrintConfiguration $configuration, array $axes): void
    {
        $values = $configuration->toArray();
        foreach ($axes as $axis) {
            if (!array_key_exists($axis, $values) || '' === trim((string) $values[$axis])) {
                throw new \InvalidArgumentException(sprintf('L’axe de prix « %s » doit être renseigné.', $axis));
            }
        }
    }

    /** @param array<string, mixed> $submitted @param list<string> $axes @param array<string, array{value: string|int|float, label: string}> $fixed @param array<string, mixed> $state */
    private function assertCustomerPricingAxesComplete(array $submitted, array $axes, array $fixed, array $state): void
    {
        $visibility = is_array($state['visibility'] ?? null) ? $state['visibility'] : [];
        foreach ($axes as $axis) {
            if (isset($fixed[$axis]) || false === ($visibility[$axis] ?? true)) {
                continue;
            }
            $value = $submitted[$axis] ?? null;
            if (null === $value || (is_string($value) && '' === trim($value))) {
                throw new \InvalidArgumentException(sprintf('Renseignez le champ requis « %s » avant de calculer le prix.', $axis));
            }
        }
    }

    /** @param list<string> $axes @param array<string, list<string|int>> $availableOptions @param list<array<string, mixed>> $fieldSchemas */
    private function hasAvailableConfiguration(array $axes, array $availableOptions, array $fieldSchemas): bool
    {
        $schemas = [];
        foreach ($fieldSchemas as $schema) {
            $schemas[$schema['code']] = $schema;
        }
        foreach ($axes as $axis) {
            $schema = $schemas[$axis] ?? null;
            if (true === ($schema['fixed'] ?? false)) {
                continue;
            }
            if ([] !== ($availableOptions[$axis] ?? [])) {
                continue;
            }
            // Free numeric/text values do not have a finite supplier catalogue of choices.
            if (!in_array($schema['type'] ?? null, ['integer', 'float', 'text'], true)) {
                return false;
            }
        }

        return [] !== $axes;
    }

    /** @param ProductRepositoryInterface<Product> $productRepository */
    private function findPrintProduct(
        string $productCode,
        ProductRepositoryInterface $productRepository,
        ChannelContextInterface $channelContext,
    ): Product {
        $channel = $channelContext->getChannel();

        if (!$channel instanceof CoreChannelInterface) {
            throw new \LogicException('The configured channel must be a Sylius core channel.');
        }

        $product = $productRepository->findOneByChannelAndCode($channel, $productCode);

        if (
            !$product instanceof Product ||
            !$product->isEnabled() ||
            !$product->hasChannel($channelContext->getChannel()) ||
            FulfillmentType::Print !== $product->getFulfillmentType()
        ) {
            throw $this->createNotFoundException('Le produit print demandé n’est pas disponible.');
        }

        return $product;
    }

    private function definitionCode(Product $product): string
    {
        return $product->getPrintDefinitionCode()
            ?? throw new \LogicException('The print product is not linked to a calculator definition.');
    }

    private function commercialVariant(Product $product): ProductVariant
    {
        $variant = $product->getEnabledVariants()->first();

        if (!$variant instanceof ProductVariant) {
            throw new \DomainException('Ce produit print ne possède aucune variante commerciale active.');
        }

        return $variant;
    }

    private function matchingQuote(
        ?string $quoteToken,
        Product $product,
        string $definitionCode,
        PrintQuoteStore $quoteStore,
        \DateTimeImmutable $now,
    ): ?StoredPrintQuote {
        if (null === $quoteToken || '' === $quoteToken) {
            return null;
        }

        try {
            $storedQuote = $quoteStore->find($quoteToken, $now);
            $variant = $this->commercialVariant($product);

            if ($variant->getCode() !== $storedQuote->variantCode() || $definitionCode !== $storedQuote->definitionCode()) {
                return null;
            }

            return $storedQuote;
        } catch (\DomainException) {
            return null;
        }
    }

    private function redirectToProduct(Product $product, Request $request, ?string $quoteToken = null): Response
    {
        $parameters = [
            'slug' => $product->getSlug(),
            '_locale' => $request->getLocale(),
        ];

        if (null !== $quoteToken) {
            $parameters['print_quote'] = $quoteToken;
            $parameters['_fragment'] = 'yoowii-print-configurator';
        }

        return $this->redirectToRoute('sylius_shop_product_show', $parameters);
    }

    private function quoteError(
        Request $request,
        Product $product,
        string $message,
        string $flashType = 'danger',
    ): Response {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                ['message' => $message],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['Cache-Control' => 'no-store'],
            );
        }

        $this->addFlash($flashType, $message);

        return $this->redirectToProduct($product, $request);
    }
}
