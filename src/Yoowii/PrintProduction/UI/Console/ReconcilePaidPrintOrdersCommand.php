<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Entity\Order\Order;
use App\Yoowii\PrintProduction\Application\CreatePrintJobsForPaidOrder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:print-jobs:reconcile', description: 'Idempotently creates missing print jobs for paid orders.')]
final class ReconcilePaidPrintOrdersCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly CreatePrintJobsForPaidOrder $creator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orders = $this->entityManager->getRepository(Order::class)->findBy(['paymentState' => 'paid']);
        $count = 0;
        foreach ($orders as $order) {
            $count += count(($this->creator)($order));
        }
        $output->writeln(sprintf('<info>%d print job(s) created.</info>', $count));

        return Command::SUCCESS;
    }
}
