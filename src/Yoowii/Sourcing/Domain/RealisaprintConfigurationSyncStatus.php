<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain;

enum RealisaprintConfigurationSyncStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
}
