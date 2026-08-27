<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Exception;

final class PricingSnapshotLocked extends \DomainException
{
    public function __construct()
    {
        parent::__construct('The pricing snapshot cannot be changed after checkout completion.');
    }
}
