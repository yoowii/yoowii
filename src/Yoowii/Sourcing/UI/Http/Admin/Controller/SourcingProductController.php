<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Entity\Product\Product;
use App\Yoowii\Pricing\Application\ResolvedPrintSourcing;
use App\Yoowii\Pricing\Application\StorefrontPrintSourcingResolver;
use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
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
    public function show(string $code, EntityManagerInterface $entityManager, SourcingProductOverviewBuilder $overviewBuilder, StorefrontPrintSourcingResolver $sourcingResolver): Response
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
        $resolvedSourcing = null;
        $resolutionError = null;
        try {
            $resolvedSourcing = $sourcingResolver->resolve($code, $now);
        } catch (\DomainException $exception) {
            $resolutionError = $exception->getMessage();
        }

        return $this->render('admin/sourcing/product_show.html.twig', [
            'page_title' => $product?->getName() ?? $code,
            'product_code' => $code,
            'product_name' => $product?->getName() ?? $code,
            'overview' => $overview,
            'route_states' => $this->routeStates($routes, $mappings, $now, $resolvedSourcing),
            'resolved_sourcing' => $resolvedSourcing,
            'resolution_error' => $resolutionError,
            'now' => $now,
        ]);
    }

    /**
     * @param list<SupplierRoute> $routes
     * @param list<SupplierProductMappingVersion> $mappings
     *
     * @return list<array{route: SupplierRoute, mode: string, status: string, reason: string}>
     */
    private function routeStates(array $routes, array $mappings, \DateTimeImmutable $now, ?ResolvedPrintSourcing $resolved): array
    {
        $states = [];
        foreach ($routes as $route) {
            $supplier = $route->supplierProduct()->supplier();
            $mapping = $this->publishedMapping($route, $mappings, $now);
            $requiresMapping = in_array($supplier->integrationMode(), [\App\Yoowii\Sourcing\Domain\SupplierIntegrationMode::Api, \App\Yoowii\Sourcing\Domain\SupplierIntegrationMode::Hybrid], true);
            $mode = $mapping instanceof SupplierProductMappingVersion
                ? ConfiguratorKind::fromConfigurationMapping($mapping->configurationMapping())->value
                : (!$requiresMapping ? ConfiguratorKind::Matrix->value : 'api_mapping_missing');

            if (!$route->isEligibleFor($route->yoowiiProductCode(), $now)) {
                $states[] = ['route' => $route, 'mode' => $mode, 'status' => 'ignored', 'reason' => 'Route, fournisseur, référence ou période inactive.'];
                continue;
            }
            if ($requiresMapping && !$mapping instanceof SupplierProductMappingVersion) {
                $states[] = ['route' => $route, 'mode' => $mode, 'status' => 'ignored', 'reason' => 'Aucun mapping publié et non archivé applicable.'];
                continue;
            }
            if ($resolved instanceof ResolvedPrintSourcing && $resolved->route() === $route) {
                $states[] = ['route' => $route, 'mode' => $mode, 'status' => 'selected', 'reason' => 'Utilisé pour les nouvelles configurations storefront.'];
                continue;
            }
            $states[] = ['route' => $route, 'mode' => $mode, 'status' => 'fallback', 'reason' => 'Sourcing de secours si les routes précédentes deviennent inéligibles.'];
        }

        return $states;
    }

    /** @param list<SupplierProductMappingVersion> $mappings */
    private function publishedMapping(SupplierRoute $route, array $mappings, \DateTimeImmutable $now): ?SupplierProductMappingVersion
    {
        foreach ($mappings as $mapping) {
            if ($mapping->supplierProduct() === $route->supplierProduct()
                && $mapping->isEffectiveAt($now)
                && null !== $mapping->publishedAt()
                && null === $mapping->archivedAt()) {
                return $mapping;
            }
        }

        return null;
    }
}
