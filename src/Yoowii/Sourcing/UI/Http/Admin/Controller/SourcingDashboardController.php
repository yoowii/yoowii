<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

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
    public function __invoke(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var list<SupplierProductMappingVersion> $allMappings */
        $allMappings = $entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([], ['effectiveFrom' => 'DESC', 'id' => 'DESC']);
        $history = $request->query->getBoolean('history');
        $mappings = $history ? $allMappings : $this->latestMappings($allMappings);

        return $this->render('admin/sourcing/dashboard.html.twig', [
            'suppliers' => $entityManager->getRepository(PrintSupplier::class)->findBy([], ['name' => 'ASC']),
            'supplier_products' => $entityManager->getRepository(SupplierProduct::class)->findBy([], ['name' => 'ASC']),
            'routes' => $entityManager->getRepository(SupplierRoute::class)->findBy([], ['yoowiiProductCode' => 'ASC', 'priority' => 'ASC']),
            'matrices' => $entityManager->getRepository(SupplierPricingMatrixVersion::class)->findBy([], ['effectiveFrom' => 'DESC']),
            'mappings' => $mappings,
            'mapping_history_visible' => $history,
            'now' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
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
