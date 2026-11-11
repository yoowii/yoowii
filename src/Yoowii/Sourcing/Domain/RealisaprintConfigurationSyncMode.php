<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain;

enum RealisaprintConfigurationSyncMode: string
{
    case Missing = 'missing';
    case All = 'all';
}
