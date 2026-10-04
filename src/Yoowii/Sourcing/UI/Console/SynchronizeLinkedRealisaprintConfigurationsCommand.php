<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Console;

use App\Yoowii\Sourcing\Application\RealisaprintCatalogSynchronizer;
use App\Yoowii\Sourcing\Application\RealisaprintMappingCompleteness;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:realisaprint:linked:sync', description: 'Refresh configurations for Realisaprint products linked to Yoowii.')]
final class SynchronizeLinkedRealisaprintConfigurationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RealisaprintCatalogSynchronizer $synchronizer,
        private readonly RealisaprintMappingCompleteness $completeness,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $catalog = $this->entityManager->getRepository(RealisaprintCatalogProduct::class);
        $seen = [];
        $failed = false;
        $lastCallAt = null;
        foreach ($this->entityManager->getRepository(SupplierRoute::class)->findAll() as $route) {
            if (!$route instanceof SupplierRoute || 'realisaprint' !== $route->supplierProduct()->supplier()->code()) {
                continue;
            }
            $providerId = $route->supplierProduct()->code();
            if (isset($seen[$providerId])) {
                continue;
            }
            $seen[$providerId] = true;
            $product = $catalog->findOneBy(['providerProductId' => $providerId]);
            if (!$product instanceof RealisaprintCatalogProduct) {
                $output->writeln(sprintf('<error>%s: missing catalogue entry.</error>', $providerId));
                $failed = true;
                continue;
            }
            // The provider limits successive calls to the same operation to one per 15 seconds.
            if (null !== $lastCallAt) {
                $wait = 16 - (time() - $lastCallAt);
                if ($wait > 0) {
                    sleep($wait);
                }
            }
            $lastCallAt = time();
            try {
                $this->synchronizer->synchronizeConfiguration($product, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
                $output->writeln(sprintf('<info>%s: configuration refreshed.</info>', $providerId));
                foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['supplierProduct' => $route->supplierProduct(), 'active' => true]) as $mapping) {
                    $definition = $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $mapping->yoowiiProductCode()]);
                    $provider = $mapping->configurationMapping()['realisaprint'] ?? null;
                    try {
                        if (!$definition instanceof PersistedPrintProductDefinition || !is_array($provider)) {
                            throw new \InvalidArgumentException('Missing product definition or supplier mapping.');
                        }
                        $this->completeness->assertComplete($definition, $product->configuration() ?? [], $provider);
                    } catch (\InvalidArgumentException $exception) {
                        $output->writeln(sprintf('<error>%s / %s: intervention required: %s</error>', $providerId, $mapping->yoowiiProductCode(), $exception->getMessage()));
                        $failed = true;
                    }
                }
            } catch (\Throwable $exception) {
                // Do not replace the last known catalogue configuration on failure.
                $output->writeln(sprintf('<error>%s: %s</error>', $providerId, $exception->getMessage()));
                $failed = true;
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
