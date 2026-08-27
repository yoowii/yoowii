<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain;

enum SupplierIntegrationMode: string
{
    case Manual = 'manual';
    case Matrix = 'matrix';
    case Api = 'api';
    case Hybrid = 'hybrid';
}
