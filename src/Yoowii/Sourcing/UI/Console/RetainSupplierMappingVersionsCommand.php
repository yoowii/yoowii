<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Console;

use App\Yoowii\Sourcing\Application\MappingVersionRetention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:sourcing:mapping-retention', description: 'Archive inactive published mappings and remove unreferenced drafts older than 90 days.')]
final class RetainSupplierMappingVersionsCommand extends Command
{
    public function __construct(private readonly MappingVersionRetention $retention)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report changes without writing them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = true === $input->getOption('dry-run');
        $result = $this->retention->apply(new \DateTimeImmutable('now', new \DateTimeZone('UTC')), $dryRun);
        $output->writeln(sprintf('%s: %d archived, %d deleted.', $dryRun ? 'Dry run' : 'Completed', $result['archived'], $result['deleted']));

        return Command::SUCCESS;
    }
}
