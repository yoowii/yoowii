<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Application\RealisaprintCatalogSynchronizer;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class RealisaprintCatalogController extends AbstractController
{
    #[Route('/realisaprint-catalog', name: 'yoowii_admin_realisaprint_catalog', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/sourcing/realisaprint_catalog.html.twig', [
            'products' => $entityManager->getRepository(RealisaprintCatalogProduct::class)->findBy([], ['archived' => 'ASC', 'name' => 'ASC']),
        ]);
    }

    #[Route('/realisaprint-catalog/synchronize', name: 'yoowii_admin_realisaprint_catalog_synchronize', methods: ['POST'])]
    public function synchronize(Request $request, CsrfTokenManagerInterface $csrf, RealisaprintCatalogSynchronizer $synchronizer): Response
    {
        $this->assertToken($request, $csrf, 'realisaprint_catalog_sync');

        try {
            $count = $synchronizer->synchronizeProducts(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->addFlash('success', sprintf('%d produit(s) Realisaprint synchronisé(s).', $count));
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Synchronisation Realisaprint impossible : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_realisaprint_catalog');
    }

    #[Route('/realisaprint-catalog/{id}', name: 'yoowii_admin_realisaprint_catalog_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->find(RealisaprintCatalogProduct::class, $id);
        if (!$product instanceof RealisaprintCatalogProduct) {
            throw $this->createNotFoundException();
        }

        return $this->render('admin/sourcing/realisaprint_catalog_show.html.twig', ['product' => $product]);
    }

    #[Route('/realisaprint-catalog/{id}/configuration/synchronize', name: 'yoowii_admin_realisaprint_catalog_configuration_synchronize', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function synchronizeConfiguration(int $id, Request $request, CsrfTokenManagerInterface $csrf, EntityManagerInterface $entityManager, RealisaprintCatalogSynchronizer $synchronizer): Response
    {
        $this->assertToken($request, $csrf, 'realisaprint_catalog_configuration_' . $id);
        $product = $entityManager->find(RealisaprintCatalogProduct::class, $id);
        if (!$product instanceof RealisaprintCatalogProduct) {
            throw $this->createNotFoundException();
        }

        try {
            $synchronizer->synchronizeConfiguration($product, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $this->addFlash('success', 'Configuration et variables Realisaprint synchronisées.');
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Lecture de configuration impossible : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('yoowii_admin_realisaprint_catalog_show', ['id' => $id]);
    }

    private function assertToken(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
