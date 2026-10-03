<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Console;

use App\Yoowii\Sourcing\Application\RealisaprintCatalogSynchronizer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:realisaprint:catalog:sync', description: 'Synchronize the read-only Realisaprint product catalogue.')]
final class SynchronizeRealisaprintCatalogCommand extends Command
{
    public function __construct(private readonly RealisaprintCatalogSynchronizer $synchronizer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $count = $this->synchronizer->synchronizeProducts(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
            $output->writeln(sprintf('<info>%d Realisaprint product(s) synchronized.</info>', $count));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('<error>Synchronization failed: ' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}
