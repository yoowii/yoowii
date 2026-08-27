<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourcingDashboardController extends AbstractController
{
    #[Route('', name: 'yoowii_admin_sourcing_dashboard', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/sourcing/dashboard.html.twig', [
            'suppliers' => $entityManager->getRepository(PrintSupplier::class)->findBy([], ['name' => 'ASC']),
            'supplier_products' => $entityManager->getRepository(SupplierProduct::class)->findBy([], ['name' => 'ASC']),
            'routes' => $entityManager->getRepository(SupplierRoute::class)->findBy([], ['yoowiiProductCode' => 'ASC', 'priority' => 'ASC']),
            'matrices' => $entityManager->getRepository(SupplierPricingMatrixVersion::class)->findBy([], ['effectiveFrom' => 'DESC']),
        ]);
    }
}
