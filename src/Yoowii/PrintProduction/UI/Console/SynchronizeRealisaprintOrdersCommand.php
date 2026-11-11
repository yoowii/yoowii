<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Application\SynchronizeRealisaprintOrderStatus;
use App\Yoowii\PrintProduction\Domain\Model\PrintJobSupplierSubmission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:print-jobs:sync-realisaprint', description: 'Synchronize confirmed Realisaprint order statuses.')]
final class SynchronizeRealisaprintOrdersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly SynchronizeRealisaprintOrderStatus $synchronize)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $changed = 0;
        foreach ($this->entityManager->getRepository(PrintJobSupplierSubmission::class)->findBy(['status' => 'submitted']) as $submission) {
            if (($this->synchronize)($submission)) {
                ++$changed;
            }
        }
        $this->entityManager->flush();
        $output->writeln(sprintf('%d dossier(s) synchronisé(s).', $changed));

        return Command::SUCCESS;
    }
}
