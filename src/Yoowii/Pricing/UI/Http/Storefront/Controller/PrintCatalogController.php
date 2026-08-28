<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Controller;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrintCatalogController extends AbstractController
{
    #[Route('/print', name: 'yoowii_shop_print_catalog', methods: ['GET'])]
    public function __invoke(BuiltInPrintProductDefinitionRegistry $definitions): Response
    {
        return $this->render('shop/print/index.html.twig', [
            'product_codes' => $definitions->codes(),
        ]);
    }
}
