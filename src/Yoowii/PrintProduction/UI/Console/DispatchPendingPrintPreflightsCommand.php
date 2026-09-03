<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Application\SchedulePrintAssetPreflight;
use App\Yoowii\PrintProduction\Domain\Model\PrintPreflightReport;
use App\Yoowii\PrintProduction\Domain\PrintPreflightStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(name: 'yoowii:print-preflight:dispatch-pending', description: 'Dispatch pending customer-artwork preflight reports.')]
final class DispatchPendingPrintPreflightsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly SchedulePrintAssetPreflight $schedule)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $reports = $this->entityManager->getRepository(PrintPreflightReport::class)->findBy(['status' => PrintPreflightStatus::Pending]);
        foreach ($reports as $report) {
            ($this->schedule)($report->printAsset());
        }
        $output->writeln(sprintf('%d contrôle(s) technique(s) envoyé(s).', count($reports)));

        return Command::SUCCESS;
    }
}
