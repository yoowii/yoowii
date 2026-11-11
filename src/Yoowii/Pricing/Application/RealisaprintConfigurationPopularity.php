<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

/** Tracks successful Realisaprint configurations without retaining their values. */
interface RealisaprintConfigurationPopularity
{
    public function record(string $configurationFingerprint, string $mappingVersion): void;
}
