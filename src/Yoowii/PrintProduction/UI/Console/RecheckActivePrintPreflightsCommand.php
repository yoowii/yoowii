<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Application\SchedulePrintAssetPreflight;
use App\Yoowii\PrintProduction\Domain\Model\PrintAsset;
use App\Yoowii\PrintProduction\Domain\PrintAssetType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:print-preflight:recheck-active', description: 'Schedule an updated preflight for every active customer artwork file.')]
final class RecheckActivePrintPreflightsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly SchedulePrintAssetPreflight $schedule)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<PrintAsset> $assets */
        $assets = $this->entityManager->getRepository(PrintAsset::class)->findBy([
            'type' => PrintAssetType::CustomerArtwork,
            'supersededAt' => null,
        ]);
        foreach ($assets as $asset) {
            ($this->schedule)($asset);
        }
        $output->writeln(sprintf('%d fichier(s) actif(s) envoyé(s) au préflight.', count($assets)));

        return Command::SUCCESS;
    }
}
