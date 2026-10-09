<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

/**
 * Serializes refreshes of one canonical Realisaprint show_variables request.
 */
interface RealisaprintVariableStateLock
{
    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function synchronized(string $resource, callable $callback): mixed;
}
