<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Console;

use App\Yoowii\PrintProduction\Application\QueuePrintJobNotification;
use App\Yoowii\PrintProduction\Application\SendPendingPrintJobNotifications;
use App\Yoowii\PrintProduction\Domain\Model\PrintJob;
use App\Yoowii\PrintProduction\Domain\PrintJobStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'yoowii:print-jobs:alert-late', description: 'Queues and sends one daily alert for each late print job.')]
final class AlertLatePrintJobsCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly QueuePrintJobNotification $queue, private readonly SendPendingPrintJobNotifications $sender) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = new \DateTimeImmutable();
        $jobs = $this->entityManager->createQueryBuilder()->select('job')->from(PrintJob::class, 'job')
            ->where('job.dueAt IS NOT NULL AND job.dueAt < :now')->andWhere('job.status NOT IN (:terminal)')
            ->setParameter('now', $now)->setParameter('terminal', [PrintJobStatus::Delivered->value, PrintJobStatus::Cancelled->value])
            ->getQuery()->getResult();
        $count = 0;
        foreach ($jobs as $job) {
            if (!$job instanceof PrintJob) { continue; }
            ($this->queue)->lateAlert($job, $now);
            ++$count;
        }
        $this->entityManager->flush();
        $sent = ($this->sender)();
        $output->writeln(sprintf('<info>%d late job(s) checked, %d notification(s) sent.</info>', $count, $sent));

        return Command::SUCCESS;
    }
}
