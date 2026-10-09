<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

/**
 * Reserves a supplier endpoint call while respecting its global interval.
 */
interface RealisaprintRequestThrottle
{
    public function acquire(string $operation): void;
}
