<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;

/** Opens one reusable inactive revision while the published mapping remains operational. */
final readonly class RealisaprintRevisionCreator
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function create(SupplierRoute $route): SupplierProductMappingVersion
    {
        $latest = $this->latestMapping($route);
        if (!$latest->isActive()) {
            if (!$latest->printProductDefinition() instanceof PersistedPrintProductDefinition || $latest->printProductDefinition()->active()) {
                throw new \DomainException('Le brouillon de révision est incomplet.');
            }

            return $latest;
        }

        $source = $latest->printProductDefinition();
        if (!$source instanceof PersistedPrintProductDefinition || !$source->active()) {
            throw new \DomainException('La définition publiée associée au mapping est introuvable.');
        }

        $revision = $source->revise();
        $mapping = new SupplierProductMappingVersion(
            $route->supplierProduct(),
            $route->yoowiiProductCode(),
            $this->nextVersion($route),
            $latest->configurationMapping(),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
            null,
            $revision,
        );
        $mapping->deactivate();
        $this->entityManager->persist($revision);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        return $mapping;
    }

    private function latestMapping(SupplierRoute $route): SupplierProductMappingVersion
    {
        $mapping = $this->entityManager->getRepository(SupplierProductMappingVersion::class)->findOneBy([
            'supplierProduct' => $route->supplierProduct(),
            'yoowiiProductCode' => $route->yoowiiProductCode(),
        ], ['id' => 'DESC']);
        if (!$mapping instanceof SupplierProductMappingVersion) {
            throw new \DomainException('Aucun mapping publié à réviser n’a été trouvé.');
        }

        return $mapping;
    }

    private function nextVersion(SupplierRoute $route): string
    {
        $max = 0;
        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy([
            'supplierProduct' => $route->supplierProduct(),
            'yoowiiProductCode' => $route->yoowiiProductCode(),
        ]) as $mapping) {
            if (preg_match('/^v(\d+)$/D', $mapping->version(), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return 'v' . ($max + 1);
    }
}
