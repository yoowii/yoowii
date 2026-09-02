<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Application\SendPendingPrintJobNotifications;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:print-jobs:send-notifications', description: 'Sends queued print production email notifications.')]
final class SendPrintJobNotificationsCommand extends Command
{
    public function __construct(private readonly SendPendingPrintJobNotifications $sender)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of notifications.', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');
        $sent = ($this->sender)(max(1, is_numeric($limit) ? (int) $limit : 100));
        $output->writeln(sprintf('<info>%d notification(s) sent.</info>', $sent));

        return Command::SUCCESS;
    }
}
