<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Yoowii\Pricing\Application\StorefrontProductSearch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'yoowii_shop_search', methods: ['GET'])]
    public function __invoke(Request $request, StorefrontProductSearch $search): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        return $this->render('shop/search/index.html.twig', [
            'query' => $query,
            'products' => mb_strlen($query) >= 2 ? $search->find($query) : [],
        ]);
    }
}
