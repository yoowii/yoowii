<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain;

enum PrintPreflightStatus: string
{
    case Pending = 'pending';
    case Passed = 'passed';
    case Warning = 'warning';
    case Failed = 'failed';
}
