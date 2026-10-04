<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Sourcing\Application\RealisaprintCatalogSynchronizer;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
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

        return $this->render('admin/sourcing/realisaprint_catalog_show.html.twig', [
            'product' => $product,
            'generated_products' => $this->generatedProducts($product, $entityManager),
        ]);
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

    /**  list<array{product: Product, stock: string, mapping_status: string, route_status: string, mapping: SupplierProductMappingVersion|null, route: SupplierRoute}> */
    private function generatedProducts(RealisaprintCatalogProduct $catalogProduct, EntityManagerInterface $entityManager): array
    {
        $supplier = $entityManager->getRepository(PrintSupplier::class)->findOneBy(['code' => 'realisaprint']);
        if (!$supplier instanceof PrintSupplier) {
            return [];
        }
        $supplierProduct = $entityManager->getRepository(SupplierProduct::class)->findOneBy(['supplier' => $supplier, 'code' => $catalogProduct->providerProductId()]);
        if (!$supplierProduct instanceof SupplierProduct) {
            return [];
        }
        $configuration = $catalogProduct->configuration() ?? [];
        $stocks = is_array($configuration['stocks'] ?? null) ? $configuration['stocks'] : [];
        $items = [];
        foreach ($entityManager->getRepository(SupplierRoute::class)->findBy(['supplierProduct' => $supplierProduct], ['id' => 'ASC']) as $route) {
            if (!$route instanceof SupplierRoute || isset($items[$route->yoowiiProductCode()])) {
                continue;
            }
            $product = $entityManager->getRepository(Product::class)->findOneBy(['code' => $route->yoowiiProductCode()]);
            if (!$product instanceof Product) {
                continue;
            }
            $mapping = $entityManager->getRepository(SupplierProductMappingVersion::class)->findOneBy(['supplierProduct' => $supplierProduct, 'yoowiiProductCode' => $route->yoowiiProductCode()], ['effectiveFrom' => 'DESC']);
            $validation = $mapping instanceof SupplierProductMappingVersion ? $entityManager->getRepository(RealisaprintMappingValidation::class)->findOneBy(['mapping' => $mapping], ['checkedAt' => 'DESC']) : null;
            $mappingConfiguration = $mapping instanceof SupplierProductMappingVersion ? $mapping->configurationMapping() : [];
            $providerMapping = is_array($mappingConfiguration['realisaprint'] ?? null) ? $mappingConfiguration['realisaprint'] : [];
            $stock = $providerMapping['stock'] ?? null;
            $stock = is_string($stock) ? $stock : '—';
            $stockLabel = is_scalar($stocks[$stock] ?? null) ? (string) $stocks[$stock] : 'Stock inconnu';
            $mappingStatus = 'absent';
            if ($mapping instanceof SupplierProductMappingVersion) {
                $mappingStatus = $route->isActive() && $mapping->isEffectiveAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) ? 'actif' : ($validation instanceof RealisaprintMappingValidation && $validation->coverageComplete() && $validation->quotePassed() ? 'validé' : 'brouillon');
            }
            $items[$route->yoowiiProductCode()] = ['product' => $product, 'stock' => $stock . ' — ' . $stockLabel, 'mapping_status' => $mappingStatus, 'route_status' => $route->isActive() ? 'publiée' : 'brouillon', 'mapping' => $mapping instanceof SupplierProductMappingVersion ? $mapping : null, 'route' => $route];
        }
        return array_values($items);
    }

    private function assertToken(Request $request, CsrfTokenManagerInterface $csrf, string $id): void
    {
        if (!$csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token')))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
