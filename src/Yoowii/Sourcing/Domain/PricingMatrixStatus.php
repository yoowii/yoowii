<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain;

enum PricingMatrixStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
