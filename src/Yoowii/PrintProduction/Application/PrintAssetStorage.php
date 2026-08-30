<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application;

interface PrintAssetStorage
{
    /** @param resource $stream */
    public function store(string $key, mixed $stream): void;
}
