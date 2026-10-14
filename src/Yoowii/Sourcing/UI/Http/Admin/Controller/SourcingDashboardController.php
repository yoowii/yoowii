<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Sourcing\Application\SourcingProductOverviewBuilder;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourcingDashboardController extends AbstractController
{
    #[Route('', name: 'yoowii_admin_sourcing_dashboard', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $entityManager, SourcingProductOverviewBuilder $overviewBuilder): Response
    {
        /** @var list<SupplierProductMappingVersion> $allMappings */
        $allMappings = $entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([], ['effectiveFrom' => 'DESC', 'id' => 'DESC']);
        $history = $request->query->getBoolean('history');
        $mappings = $history ? $allMappings : $this->latestMappings($allMappings);

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $routes = $entityManager->getRepository(SupplierRoute::class)->findBy([], ['yoowiiProductCode' => 'ASC', 'priority' => 'ASC']);
        $matrices = $entityManager->getRepository(SupplierPricingMatrixVersion::class)->findBy([], ['effectiveFrom' => 'DESC']);
        $overview = $overviewBuilder->build($routes, $allMappings, $matrices, $now);
        /** @var list<Product> $catalogProducts */
        $catalogProducts = [] === $overview ? [] : $entityManager->getRepository(Product::class)->findBy(['code' => array_keys($overview)]);
        $productNames = [];
        foreach ($catalogProducts as $catalogProduct) {
            $productNames[$catalogProduct->getCode()] = $catalogProduct->getName();
        }
        $section = $request->query->get('section', 'overview');
        $section = in_array($section, ['overview', 'products', 'suppliers', 'mappings', 'routes', 'matrices'], true) ? $section : 'overview';
        $query = trim((string) $request->query->get('q', ''));
        $supplierFilter = trim((string) $request->query->get('supplier', ''));
        $healthFilter = trim((string) $request->query->get('health', ''));
        $includeInactive = $request->query->getBoolean('inactive');
        $products = [];
        foreach ($overview as $code => $item) {
            /** @var array{primary_route: SupplierRoute|null, errors: list<string>, recommendations: list<string>, future: array{routes: list<SupplierRoute>}} $item */
            $primary = $item['primary_route'];
            $supplier = $primary instanceof SupplierRoute ? $primary->supplierProduct()->supplier() : null;
            if ('' !== $query && !str_contains(mb_strtolower($code), mb_strtolower($query))) {
                continue;
            }
            if ('' !== $supplierFilter && (null === $supplier || $supplier->code() !== $supplierFilter)) {
                continue;
            }
            $health = [] !== $item['errors'] ? 'blocked' : ([] !== $item['recommendations'] ? 'attention' : 'healthy');
            if ('' !== $healthFilter && $health !== $healthFilter) {
                continue;
            }
            if (!$includeInactive && null === $primary && [] === $item['future']['routes']) {
                continue;
            }
            $products[$code] = $item + ['health' => $health, 'name' => $productNames[$code] ?? $code];
        }
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = 25;
        $productCount = count($products);
        $pageCount = max(1, (int) ceil($productCount / $perPage));
        $page = min($page, $pageCount);
        $products = array_slice($products, ($page - 1) * $perPage, $perPage, true);

        return $this->render('admin/sourcing/dashboard.html.twig', [
            'suppliers' => $entityManager->getRepository(PrintSupplier::class)->findBy([], ['name' => 'ASC']),
            'supplier_products' => $entityManager->getRepository(SupplierProduct::class)->findBy([], ['name' => 'ASC']),
            'routes' => $routes,
            'matrices' => $matrices,
            'mappings' => $mappings,
            'mapping_history_visible' => $history,
            'now' => $now,
            'section' => $section,
            'products' => $products,
            'query' => $query,
            'supplier_filter' => $supplierFilter,
            'health_filter' => $healthFilter,
            'include_inactive' => $includeInactive,
            'page' => $page,
            'page_count' => $pageCount,
            'product_count' => $productCount,
        ]);
    }

    /**
     * @param list<SupplierProductMappingVersion> $mappings
     *
     * @return array<int, SupplierProductMappingVersion>
     */
    private function latestMappings(array $mappings): array
    {
        $latest = [];
        foreach ($mappings as $mapping) {
            $key = $mapping->yoowiiProductCode() . ':' . $mapping->supplierProduct()->id();
            $latest[$key] ??= $mapping;
        }

        return array_values($latest);
    }
}
