<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Entity\Product\Product;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SearchSuggestController extends AbstractController
{
    /** @param ProductRepositoryInterface<Product> $productRepository */
    #[Route('/search/suggest', name: 'yoowii_shop_search_suggest', methods: ['GET'])]
    public function __invoke(
        Request $request,
        ProductRepositoryInterface $productRepository,
        ChannelContextInterface $channelContext,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->json(['products' => [], 'suggestions' => []]);
        }

        $needle = mb_strtolower($query);
        $channel = $channelContext->getChannel();
        $locale = $request->getLocale();
        $products = [];

        foreach ($productRepository->findBy(['enabled' => true]) as $product) {
            if (!$product instanceof Product || !$product->hasChannel($channel)) {
                continue;
            }

            $name = $product->getName() ?? '';
            if (!str_contains(mb_strtolower($name . ' ' . $product->getCode()), $needle)) {
                continue;
            }

            $products[] = [
                'name' => $name,
                'url' => $urlGenerator->generate('sylius_shop_product_show', ['slug' => $product->getSlug(), '_locale' => $locale]),
            ];

            if (3 === count($products)) {
                break;
            }
        }

        return $this->json([
            'products' => $products,
            'suggestions' => array_map(static fn (array $product): string => $product['name'], $products),
        ])->setPublic()->setMaxAge(60);
    }
}
