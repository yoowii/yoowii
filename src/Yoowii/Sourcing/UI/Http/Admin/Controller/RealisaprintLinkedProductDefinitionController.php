<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RealisaprintLinkedProductDefinitionController extends AbstractController
{
    #[Route('/realisaprint-catalog/{id}/linked-products/{routeId}/definition', name: 'yoowii_admin_realisaprint_linked_product_definition', requirements: ['id' => '\\d+', 'routeId' => '\\d+'], methods: ['GET'])]
    public function show(int $id, int $routeId, EntityManagerInterface $entityManager): Response
    {
        $catalogProduct = $entityManager->find(RealisaprintCatalogProduct::class, $id);
        $route = $entityManager->find(SupplierRoute::class, $routeId);
        if (!$catalogProduct instanceof RealisaprintCatalogProduct || !$route instanceof SupplierRoute ||
            'realisaprint' !== $route->supplierProduct()->supplier()->code() ||
            $catalogProduct->providerProductId() !== $route->supplierProduct()->code()) {
            throw $this->createNotFoundException();
        }

        $definition = $entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy([
            'productCode' => $route->yoowiiProductCode(),
        ]);
        if (!$definition instanceof PersistedPrintProductDefinition) {
            throw $this->createNotFoundException('Définition de configurateur introuvable.');
        }

        return $this->render('admin/sourcing/realisaprint_linked_product_definition.html.twig', [
            'catalog_product' => $catalogProduct,
            'route' => $route,
            'definition' => $definition,
        ]);
    }
}
