<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Pricing\Application\PrintConfigurationCatalog;
use App\Yoowii\Pricing\Application\PrintQuoteService;
use App\Yoowii\Pricing\Application\Quote\PrintQuoteStore;
use App\Yoowii\Pricing\Application\Quote\StoredPrintQuote;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\UI\Http\Storefront\Form\PrintConfiguratorType;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Model\ChannelInterface as CoreChannelInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintProductConfiguratorController extends AbstractController
{
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
    ): Response {
        $product = $this->findPrintProduct($productCode, $productRepository, $channelContext);
        $definitionCode = $this->definitionCode($product);
        $definition = $definitions->get($definitionCode);
        $currencyCode = $currencyContext->getCurrencyCode();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $availableOptions = $configurationCatalog->availableOptions($definition, $currencyCode, $now);
        $form = $this->createConfiguratorForm($productCode, $availableOptions, $request);
        $form->handleRequest($request);

        if ([] === $availableOptions || in_array([], $availableOptions, true)) {
            $this->addFlash('warning', 'Ce produit n’a actuellement aucune configuration tarifaire disponible.');

            return $this->redirectToProduct($product, $request);
        }

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'La configuration sélectionnée est invalide.');

            return $this->redirectToProduct($product, $request);
        }

        try {
            $formData = $form->getData();

            if (!is_array($formData)) {
                throw new \InvalidArgumentException('La configuration reçue est invalide.');
            }

            $values = $formData;
            /** @var array<string, mixed> $values */
            $configuration = $definition->configure($values);
            $quote = $quoteService->quote(
                $configuration,
                $pricingPolicyProvider->get(),
                $currencyCode,
                $now,
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
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToProduct($product, $request);
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
    ): Response {
        $product = $this->findPrintProduct($productCode, $productRepository, $channelContext);
        $definitionCode = $this->definitionCode($product);
        $definition = $definitions->get($definitionCode);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $availableOptions = $configurationCatalog->availableOptions(
            $definition,
            $currencyContext->getCurrencyCode(),
            $now,
        );
        $storedQuote = $this->matchingQuote($quoteToken, $product, $definitionCode, $quoteStore, $now);
        $configuration = $storedQuote?->pricingSnapshot()->configuration()['options'] ?? [];
        $form = $this->createConfiguratorForm(
            $productCode,
            $availableOptions,
            $request,
            is_array($configuration) ? $configuration : [],
        );

        return $this->render('shop/product/show/print_configurator.html.twig', [
            'product' => $product,
            'form' => $form->createView(),
            'available' => [] !== $availableOptions && !in_array([], $availableOptions, true),
            'stored_quote' => $storedQuote,
            'quote_token' => null !== $storedQuote ? $quoteToken : null,
            'configuration' => is_array($configuration) ? $configuration : [],
        ]);
    }

    /**
     * @param array<string, list<string|int>> $availableOptions
     * @param array<string, mixed> $data
     */
    private function createConfiguratorForm(
        string $productCode,
        array $availableOptions,
        Request $request,
        array $data = [],
    ): \Symfony\Component\Form\FormInterface {
        return $this->createForm(PrintConfiguratorType::class, $data, [
            'action' => $this->generateUrl('yoowii_shop_print_product_quote', [
                'productCode' => $productCode,
                '_locale' => $request->getLocale(),
            ]),
            'method' => 'POST',
            'option_choices' => $availableOptions,
            'product_code' => $productCode,
        ]);
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
}
