<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Application\Validation\SupplierRouteSchedule;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\SupplierRouteData;
use App\Yoowii\Sourcing\UI\Http\Admin\Form\SupplierRouteType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class SupplierRouteController extends AbstractController
{
    #[Route('/routes/new', name: 'yoowii_admin_sourcing_route_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SupplierRouteSchedule $routeSchedule,
    ): Response {
        $data = new SupplierRouteData();
        $data->effectiveFrom = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $form = $this->createForm(SupplierRouteType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $supplierProduct = $data->supplierProduct ?? throw new \LogicException('Validated supplier product is missing.');
            $effectiveFrom = $data->effectiveFrom ?? throw new \LogicException('Validated start date is missing.');

            if ($data->active && $routeSchedule->hasConflict(
                $this->activeRoutes($entityManager, $data->productCode, $data->priority),
                $data->productCode,
                $data->priority,
                $effectiveFrom,
                $data->effectiveUntil,
            )) {
                $form->get('priority')->addError(new FormError(
                    'Une route active utilise déjà cette priorité sur une période qui se chevauche.',
                ));

                return $this->render('admin/sourcing/form.html.twig', [
                    'page_title' => 'Nouvelle route fournisseur',
                    'form' => $form,
                    'back_route' => 'yoowii_admin_sourcing_dashboard',
                ]);
            }

            $route = new SupplierRoute(
                $data->productCode,
                $supplierProduct,
                $data->priority,
                $effectiveFrom,
                $data->effectiveUntil,
            );

            if (!$data->active) {
                $route->deactivate();
            }

            $entityManager->persist($route);
            $entityManager->flush();
            $this->addFlash('success', 'La route fournisseur a été créée.');

            return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
        }

        return $this->render('admin/sourcing/form.html.twig', [
            'page_title' => 'Nouvelle route fournisseur',
            'form' => $form,
            'back_route' => 'yoowii_admin_sourcing_dashboard',
        ]);
    }

    #[Route('/routes/{id}/toggle', name: 'yoowii_admin_sourcing_route_toggle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function toggle(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        SupplierRouteSchedule $routeSchedule,
    ): Response {
        $route = $entityManager->find(SupplierRoute::class, $id);

        if (!$route instanceof SupplierRoute) {
            throw $this->createNotFoundException();
        }

        $token = new CsrfToken(sprintf('toggle_route_%d', $id), (string) $request->request->get('_token'));

        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($route->isActive()) {
            $route->deactivate();
        } else {
            if ($routeSchedule->hasConflict(
                $this->activeRoutes($entityManager, $route->yoowiiProductCode(), $route->priority()),
                $route->yoowiiProductCode(),
                $route->priority(),
                $route->effectiveFrom(),
                $route->effectiveUntil(),
                $route->id(),
            )) {
                $this->addFlash('danger', 'Cette route ne peut pas être activée : sa priorité chevauche une route active.');

                return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
            }

            $route->activate();
        }
        $entityManager->flush();
        $this->addFlash('success', 'L’état de la route a été mis à jour.');

        return $this->redirectToRoute('yoowii_admin_sourcing_dashboard');
    }

    /** @return list<SupplierRoute> */
    private function activeRoutes(
        EntityManagerInterface $entityManager,
        string $productCode,
        int $priority,
    ): array {
        /** @var list<SupplierRoute> $routes */
        $routes = $entityManager->getRepository(SupplierRoute::class)->findBy([
            'yoowiiProductCode' => $productCode,
            'priority' => $priority,
            'active' => true,
        ]);

        return $routes;
    }
}
