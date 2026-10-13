<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Application\RealisaprintRevisionCreator;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RealisaprintRevisionController extends AbstractController
{
    #[Route('/realisaprint-publications/{routeId}/revision', name: 'yoowii_admin_realisaprint_revision_create', requirements: ['routeId' => '\\d+'], methods: ['POST'])]
    public function create(int $routeId, Request $request, EntityManagerInterface $entityManager, CsrfTokenManagerInterface $csrf, RealisaprintRevisionCreator $revisions): Response
    {
        $route = $entityManager->find(SupplierRoute::class, $routeId);
        if (!$route instanceof SupplierRoute || 'realisaprint' !== $route->supplierProduct()->supplier()->code()) {
            throw $this->createNotFoundException();
        }
        if (!$csrf->isTokenValid(new CsrfToken('create_realisaprint_revision_' . $routeId, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        try {
            $mapping = $revisions->create($route);
            $this->addFlash('success', 'La révision brouillon a été créée. La version publiée reste en service jusqu’à la prochaine publication.');

            $catalog = $entityManager->getRepository(RealisaprintCatalogProduct::class)->findOneBy(['providerProductId' => $route->supplierProduct()->code()]);
            if (!$catalog instanceof RealisaprintCatalogProduct || null === $catalog->id()) {
                throw $this->createNotFoundException('Produit catalogue Realisaprint introuvable.');
            }

            return $this->redirectToRoute('yoowii_admin_realisaprint_linked_product_definition', ['id' => $catalog->id(), 'routeId' => $route->id()]);
        } catch (\DomainException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }
    }
}
