<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Yoowii\Pricing\Application\StorefrontProductSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SearchSuggestController extends AbstractController
{
    #[Route('/search/suggest', name: 'yoowii_shop_search_suggest', methods: ['GET'])]
    public function __invoke(
        Request $request,
        StorefrontProductSearch $search,
        UrlGeneratorInterface $urlGenerator,
    ): JsonResponse {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->json(['products' => [], 'suggestions' => []]);
        }

        $locale = $request->getLocale();
        $products = [];

        foreach ($search->find($query, 3) as $product) {
            $name = $product->getName() ?? '';
            $products[] = [
                'name' => $name,
                'url' => $urlGenerator->generate('sylius_shop_product_show', ['slug' => $product->getSlug(), '_locale' => $locale]),
            ];
        }

        return $this->json([
            'products' => $products,
            'suggestions' => array_map(static fn (array $product): string => $product['name'], $products),
        ])->setPublic()->setMaxAge(60);
    }
}
