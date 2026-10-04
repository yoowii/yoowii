<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintDraftRepair;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RealisaprintDraftRepairController extends AbstractController
{
    #[Route('/realisaprint-publications/{routeId}/repair', name: 'yoowii_admin_realisaprint_draft_repair', requirements: ['routeId' => '\d+'], methods: ['GET', 'POST'])]
    public function repair(
        int $routeId,
        Request $request,
        EntityManagerInterface $entityManager,
        RealisaprintDraftRepair $repair,
        CsrfTokenManagerInterface $csrf,
    ): Response {
        $route = $entityManager->find(SupplierRoute::class, $routeId);
        if (!$route instanceof SupplierRoute || 'realisaprint' !== $route->supplierProduct()->supplier()->code()) {
            throw $this->createNotFoundException();
        }
        $catalog = $entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);
        $definition = $entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $route->yoowiiProductCode()]);
        if (!$catalog instanceof RealisaprintCatalogProduct || !$definition instanceof PersistedPrintProductDefinition) {
            throw $this->createNotFoundException('Catalogue ou définition du produit lié introuvable.');
        }
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$csrf->isTokenValid(new CsrfToken('repair_realisaprint_draft_' . $routeId, $token))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            try {
                $mapping = $repair->repair($route, $catalog, $definition, $request->request->all('sample'));
                $this->addFlash('success', 'Le brouillon a été corrigé. Relance le contrôle de couverture et du prix avant publication.');

                return $this->redirectToRoute('yoowii_admin_realisaprint_mapping_validation', ['id' => $mapping->id()]);
            } catch (\DomainException|\InvalidArgumentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }
        try {
            $changes = $repair->preview($route, $catalog, $definition);
            $error = null;
        } catch (\DomainException $exception) {
            $changes = [];
            $error = $exception->getMessage();
        }

        return $this->render('admin/sourcing/realisaprint_draft_repair.html.twig', [
            'route' => $route, 'catalog' => $catalog, 'changes' => $changes, 'error' => $error,
        ]);
    }
}
