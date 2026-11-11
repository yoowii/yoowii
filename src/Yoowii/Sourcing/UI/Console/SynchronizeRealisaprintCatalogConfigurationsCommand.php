<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Console;

use App\Yoowii\Sourcing\Application\RealisaprintConfigurationSyncProcessor;
use App\Yoowii\Sourcing\Application\RealisaprintConfigurationSyncRunFactory;
use App\Yoowii\Sourcing\Domain\RealisaprintConfigurationSyncMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:realisaprint:catalog-configurations:sync', description: 'Synchronize cached configurations for the Realisaprint catalogue.')]
final class SynchronizeRealisaprintCatalogConfigurationsCommand extends Command
{
    public function __construct(private readonly RealisaprintConfigurationSyncRunFactory $runs, private readonly RealisaprintConfigurationSyncProcessor $processor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('mode', null, InputOption::VALUE_REQUIRED, 'missing or all', RealisaprintConfigurationSyncMode::Missing->value);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modeValue = $input->getOption('mode');
        $mode = is_string($modeValue) ? RealisaprintConfigurationSyncMode::tryFrom($modeValue) : null;
        if (null === $mode) {
            $output->writeln('<error>The --mode option must be "missing" or "all".</error>');

            return Command::INVALID;
        }

        try {
            $run = $this->runs->create($mode);
            $this->processor->process($run);
        } catch (\Throwable $exception) {
            $output->writeln('<error>Synchronization failed: ' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }
        foreach ($run->report() as $entry) {
            $output->writeln(sprintf('%s: %s%s', $entry['provider_product_id'], $entry['outcome'], isset($entry['detail']) ? ' - ' . $entry['detail'] : ''));
        }
        $summary = $run->summary();
        $output->writeln(sprintf('Report: %d synchronized, %d failed.', $summary['synchronized'], $summary['failed']));

        return 0 === $summary['failed'] ? Command::SUCCESS : Command::FAILURE;
    }
}
