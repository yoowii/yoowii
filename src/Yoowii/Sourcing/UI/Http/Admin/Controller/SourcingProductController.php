<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Sourcing\Application\SourcingProductOverviewBuilder;
use App\Yoowii\Sourcing\Domain\Model\SupplierPricingMatrixVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SourcingProductController extends AbstractController
{
    #[Route('/products/{code}', name: 'yoowii_admin_sourcing_product_show', requirements: ['code' => 'PRINT_[A-Z0-9_]+'], methods: ['GET'])]
    public function show(string $code, EntityManagerInterface $entityManager, SourcingProductOverviewBuilder $overviewBuilder): Response
    {
        /** @var list<SupplierRoute> $routes */
        $routes = $entityManager->getRepository(SupplierRoute::class)->findBy(['yoowiiProductCode' => $code], ['priority' => 'ASC']);
        /** @var list<SupplierProductMappingVersion> $mappings */
        $mappings = $entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $code], ['effectiveFrom' => 'DESC']);
        /** @var list<SupplierPricingMatrixVersion> $matrices */
        $matrices = $entityManager->getRepository(SupplierPricingMatrixVersion::class)->findBy([], ['effectiveFrom' => 'DESC']);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $overview = $overviewBuilder->build($routes, $mappings, $matrices, $now)[$code] ?? null;
        if (!is_array($overview)) {
            throw $this->createNotFoundException();
        }

        /** @var Product|null $product */
        $product = $entityManager->getRepository(Product::class)->findOneBy(['code' => $code]);

        return $this->render('admin/sourcing/product_show.html.twig', [
            'page_title' => $product?->getName() ?? $code,
            'product_code' => $code,
            'product_name' => $product?->getName() ?? $code,
            'overview' => $overview,
            'now' => $now,
        ]);
    }
}
