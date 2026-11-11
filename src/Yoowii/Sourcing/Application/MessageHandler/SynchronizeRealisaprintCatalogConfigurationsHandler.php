<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\MessageHandler;

use App\Yoowii\Sourcing\Application\Message\SynchronizeRealisaprintCatalogConfigurations;
use App\Yoowii\Sourcing\Application\RealisaprintConfigurationSyncProcessor;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintConfigurationSyncRun;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SynchronizeRealisaprintCatalogConfigurationsHandler
{
    public function __construct(private EntityManagerInterface $entityManager, private RealisaprintConfigurationSyncProcessor $processor)
    {
    }

    public function __invoke(SynchronizeRealisaprintCatalogConfigurations $message): void
    {
        $run = $this->entityManager->find(RealisaprintConfigurationSyncRun::class, $message->runId);
        if ($run instanceof RealisaprintConfigurationSyncRun) {
            $this->processor->process($run);
        }
    }
}
