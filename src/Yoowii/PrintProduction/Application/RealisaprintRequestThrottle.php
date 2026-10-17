<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

/**
 * Reserves a supplier endpoint call while respecting its global interval.
 */
interface RealisaprintRequestThrottle
{
    /**
     * Acquires the single, global Realisaprint API slot.
     *
     * The returned lease must be completed after the provider response has been
     * consumed, so the cooldown starts after (not before) that response.
     */
    public function acquire(): string;

    public function complete(string $lease): void;
}
