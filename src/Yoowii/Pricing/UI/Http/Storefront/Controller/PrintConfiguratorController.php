<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Pricing\Application\PrintConfigurationCatalog;
use App\Yoowii\Pricing\Application\PrintQuoteService;
use App\Yoowii\Pricing\Application\Quote\PrintQuoteStore;
use App\Yoowii\Pricing\Application\RetailPrintPricingPolicyProvider;
use App\Yoowii\Pricing\UI\Http\Storefront\Form\PrintConfiguratorType;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintConfiguratorController extends AbstractController
{
    #[Route('/print/{productCode}', name: 'yoowii_shop_print_configure', requirements: ['productCode' => 'PRINT_[A-Z0-9_]+'], methods: ['GET', 'POST'])]
    public function __invoke(
        string $productCode,
        Request $request,
        BuiltInPrintProductDefinitionRegistry $definitions,
        PrintConfigurationCatalog $configurationCatalog,
        PrintQuoteService $quoteService,
        RetailPrintPricingPolicyProvider $pricingPolicyProvider,
        PrintQuoteStore $quoteStore,
        CurrencyContextInterface $currencyContext,
    ): Response {
        try {
            $definition = $definitions->get($productCode);
        } catch (\InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        $currencyCode = $currencyContext->getCurrencyCode();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $availableOptions = $configurationCatalog->availableOptions($definition, $currencyCode, $now);
        $available = [] !== $availableOptions && !in_array([], $availableOptions, true);
        $form = $this->createForm(PrintConfiguratorType::class, null, [
            'option_choices' => $availableOptions,
            'product_code' => $productCode,
        ]);
        $form->handleRequest($request);
        $quote = null;
        $quoteToken = null;

        if ($available && $form->isSubmitted() && $form->isValid()) {
            try {
                $values = $form->getData();

                if (!is_array($values)) {
                    throw new \InvalidArgumentException('La configuration reçue est invalide.');
                }

                /** @var array<string, mixed> $values */
                $configuration = $definition->configure($values);
                $quote = $quoteService->quote(
                    $configuration,
                    $pricingPolicyProvider->get(),
                    $currencyCode,
                    $now,
                );
                $quoteToken = $quoteStore->issue($productCode, $quote->pricingSnapshot(), $now);
            } catch (\InvalidArgumentException|\DomainException $exception) {
                $form->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('shop/print/configurator.html.twig', [
            'page_title' => $this->productName($productCode),
            'product_code' => $productCode,
            'form' => $form,
            'available' => $available,
            'quote' => $quote,
            'quote_token' => $quoteToken,
        ]);
    }

    private function productName(string $productCode): string
    {
        return match ($productCode) {
            'PRINT_FLYER' => 'Flyers personnalisés',
            'PRINT_BUSINESS_CARD' => 'Cartes de visite',
            default => 'Produit imprimé',
        };
    }
}
