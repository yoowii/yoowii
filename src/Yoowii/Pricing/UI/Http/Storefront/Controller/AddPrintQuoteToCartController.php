<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Order\OrderItem;
use App\Entity\Product\Product;
use App\Entity\Product\ProductVariant;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\Quote\Exception\PrintQuoteUnavailable;
use App\Yoowii\Pricing\Application\Quote\PrintQuoteStore;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Factory\CartItemFactoryInterface;
use Sylius\Component\Core\Model\OrderItemInterface as CoreOrderItemInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Order\Modifier\OrderModifierInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AddPrintQuoteToCartController extends AbstractController
{
    #[Route('/print/quote/{token}/cart', name: 'yoowii_shop_print_add_to_cart', requirements: ['token' => '[A-Za-z0-9_-]{43}'], methods: ['POST'])]
    public function __invoke(
        string $token,
        Request $request,
        PrintQuoteStore $quoteStore,
        ProductVariantRepositoryInterface $productVariantRepository,
        CartContextInterface $cartContext,
        CartItemFactoryInterface $cartItemFactory,
        OrderItemQuantityModifierInterface $quantityModifier,
        OrderModifierInterface $orderModifier,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        ChannelContextInterface $channelContext,
    ): Response {
        $csrfToken = new CsrfToken(
            sprintf('add_print_quote_%s', $token),
            (string) $request->request->get('_token'),
        );

        if (!$csrfTokenManager->isTokenValid($csrfToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        try {
            $storedQuote = $quoteStore->find($token, $now);
        } catch (PrintQuoteUnavailable $exception) {
            $this->addFlash('danger', $exception->getMessage());

            return $this->redirectToRoute('yoowii_shop_print_catalog', ['_locale' => $request->getLocale()]);
        }

        $variant = $productVariantRepository->findOneBy(['code' => $storedQuote->variantCode()]);

        if (!$variant instanceof ProductVariant || !$variant->isEnabled()) {
            $this->addFlash('danger', 'Ce produit imprimé n’est pas disponible dans le catalogue.');

            return $this->redirectToConfigurator($storedQuote->variantCode(), $request);
        }

        $product = $variant->getProduct();

        if (
            !$product instanceof Product
            || !$product->isEnabled()
            || !$product->hasChannel($channelContext->getChannel())
            || FulfillmentType::Print !== $product->getFulfillmentType()
        ) {
            throw $this->createNotFoundException('La variante ne correspond pas à un produit print Yoowii.');
        }

        $cart = $cartContext->getCart();

        foreach ($cart->getItems() as $existingItem) {
            if (!$existingItem instanceof CoreOrderItemInterface) {
                throw new \LogicException('The print cart must contain Sylius core order items.');
            }

            $existingProduct = $existingItem->getVariant()?->getProduct();

            if (!$existingProduct instanceof Product || FulfillmentType::Print !== $existingProduct->getFulfillmentType()) {
                $this->addFlash('warning', 'Finalisez ou videz votre panier actuel avant d’ajouter un produit imprimé.');

                return $this->redirectToRoute('sylius_shop_cart_summary', ['_locale' => $request->getLocale()]);
            }
        }

        $snapshot = $storedQuote->pricingSnapshot();
        $cartCurrency = $cart->getCurrencyCode();

        if (null === $cartCurrency) {
            $cart->setCurrencyCode($snapshot->currencyCode());
        } elseif ($cartCurrency !== $snapshot->currencyCode()) {
            $this->addFlash('danger', 'La devise du devis ne correspond plus à celle du panier. Recalculez le prix.');

            return $this->redirectToConfigurator($storedQuote->variantCode(), $request);
        }

        $cartItem = $cartItemFactory->createNew();

        if (!$cartItem instanceof OrderItem) {
            throw new \LogicException('The configured cart item factory must create App\\Entity\\Order\\OrderItem instances.');
        }

        $cartItem->setVariant($variant);
        $quantityModifier->modify($cartItem, 1);
        $cartItem->replacePricingSnapshot($snapshot);
        $orderModifier->addToOrder($cart, $cartItem);
        $entityManager->persist($cart);
        $entityManager->flush();
        $quoteStore->consume($token, $now);
        $this->addFlash('success', 'Votre produit configuré a été ajouté au panier.');

        return $this->redirectToRoute('sylius_shop_cart_summary', ['_locale' => $request->getLocale()]);
    }

    private function redirectToConfigurator(string $productCode, Request $request): Response
    {
        return $this->redirectToRoute('yoowii_shop_print_configure', [
            'productCode' => $productCode,
            '_locale' => $request->getLocale(),
        ]);
    }
}
