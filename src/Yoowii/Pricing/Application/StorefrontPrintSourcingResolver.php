<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\ConfiguratorKind;
use App\Yoowii\Sourcing\Domain\FixedSupplierRouter;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use App\Yoowii\Sourcing\Domain\Repository\SupplierRouteRepository;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Doctrine\ORM\EntityManagerInterface;

/** Resolves the first usable storefront sourcing, ordered by route priority. */
final readonly class StorefrontPrintSourcingResolver
{
    public function __construct(
        private SupplierRouteRepository $routes,
        private FixedSupplierRouter $router,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function resolve(string $productCode, \DateTimeImmutable $at): ResolvedPrintSourcing
    {
        $routes = $this->router->rank($productCode, $at, $this->routes->findCandidates($productCode, $at));

        foreach ($routes as $route) {
            $mode = $route->supplierProduct()->supplier()->integrationMode();
            if (in_array($mode, [SupplierIntegrationMode::Matrix, SupplierIntegrationMode::Manual], true)) {
                return new ResolvedPrintSourcing($route, ConfiguratorKind::Matrix);
            }

            $mapping = $this->publishedMapping($productCode, $route, $at);
            if (!$mapping instanceof SupplierProductMappingVersion) {
                // An API route without a published mapping cannot produce a
                // deterministic customer configuration, so try its fallback.
                continue;
            }

            return new ResolvedPrintSourcing(
                $route,
                ConfiguratorKind::fromConfigurationMapping($mapping->configurationMapping()),
                $mapping,
            );
        }

        throw new \DomainException(sprintf('Aucun sourcing publiable n’est disponible pour le produit « %s ».', $productCode));
    }

    private function publishedMapping(string $productCode, SupplierRoute $route, \DateTimeImmutable $at): ?SupplierProductMappingVersion
    {
        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['yoowiiProductCode' => $productCode, 'active' => true]) as $mapping) {
            if (!$mapping instanceof SupplierProductMappingVersion || $mapping->supplierProduct() !== $route->supplierProduct()) {
                continue;
            }

            if ($mapping->isEffectiveAt($at) && null !== $mapping->publishedAt() && null === $mapping->archivedAt()) {
                return $mapping;
            }
        }

        return null;
    }
}
