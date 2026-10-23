<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\Configurator\PrintConfiguratorDriverRegistry;
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
        private PrintConfiguratorDriverRegistry $driverRegistry,
    ) {
    }

    public function resolve(string $productCode, \DateTimeImmutable $at): ResolvedPrintSourcing
    {
        $routes = $this->router->rank($productCode, $at, $this->routes->findCandidates($productCode, $at));

        foreach ($routes as $route) {
            $mode = $route->supplierProduct()->supplier()->integrationMode();
            if (in_array($mode, [SupplierIntegrationMode::Matrix, SupplierIntegrationMode::Manual], true)) {
                return new ResolvedPrintSourcing($route, ConfiguratorKind::Matrix, 'matrix');
            }

            $mapping = $this->publishedMapping($productCode, $route, $at);
            if (!$mapping instanceof SupplierProductMappingVersion) {
                // An API route without a published mapping cannot produce a
                // deterministic customer configuration, so try its fallback.
                continue;
            }

            $kind = ConfiguratorKind::fromConfigurationMapping($mapping->configurationMapping());
            try {
                $driverCode = $this->driverCode($mapping, $kind);
                $this->driverRegistry->get($driverCode);
            } catch (\DomainException) {
                // A mapping that references a driver which is not installed is
                // not a safe storefront source; evaluate its fallback route.
                continue;
            }

            return new ResolvedPrintSourcing(
                $route,
                $kind,
                $driverCode,
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

    private function driverCode(SupplierProductMappingVersion $mapping, ConfiguratorKind $kind): string
    {
        $declared = $mapping->configurationMapping()['yoowii']['configurator_driver'] ?? null;
        if (is_string($declared) && preg_match('/^[a-z][a-z0-9_]*$/D', $declared)) {
            return $declared;
        }

        return match ($kind) {
            ConfiguratorKind::SupplierApi => 'realisaprint_classic',
            ConfiguratorKind::Prescript => 'realisaprint_prescript',
            ConfiguratorKind::Matrix => 'matrix',
        };
    }
}
