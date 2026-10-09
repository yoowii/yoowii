<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Console;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintInitialDisplayStateBuilder;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Refreshes only the published, deterministic display configurations.
 *
 * This is intentionally a console task instead of a Messenger message: no
 * browser configuration or customer free-text value is serialized or retained
 * outside its request.
 */
#[AsCommand(name: 'yoowii:realisaprint:display-state:warm', description: 'Refresh the published Realisaprint display states without customer configuration data.')]
final class WarmRealisaprintDisplayStatesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RealisaprintInitialDisplayStateBuilder $displayStateBuilder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $failed = false;

        foreach ($this->entityManager->getRepository(SupplierProductMappingVersion::class)->findBy(['active' => true]) as $mapping) {
            if (!$mapping instanceof SupplierProductMappingVersion || 'realisaprint' !== $mapping->supplierProduct()->supplier()->code() || !$mapping->isEffectiveAt($now)) {
                continue;
            }

            $definition = $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy([
                'productCode' => $mapping->yoowiiProductCode(),
                'active' => true,
            ]);
            if (!$definition instanceof PersistedPrintProductDefinition) {
                $output->writeln(sprintf('<error>%s: active product definition is missing.</error>', $mapping->yoowiiProductCode()));
                $failed = true;

                continue;
            }

            try {
                $state = $this->displayStateBuilder->build($mapping, $definition, $now);
                if (isset($state['error'])) {
                    throw new \RuntimeException(is_string($state['error']) ? $state['error'] : 'Realisaprint display state could not be refreshed.');
                }
                $mapping->storeInitialDisplayState($state);
                $this->entityManager->flush();
                $output->writeln(sprintf('<info>%s: display state refreshed.</info>', $mapping->yoowiiProductCode()));
            } catch (\Throwable $exception) {
                $output->writeln(sprintf('<error>%s: %s</error>', $mapping->yoowiiProductCode(), $exception->getMessage()));
                $failed = true;
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
