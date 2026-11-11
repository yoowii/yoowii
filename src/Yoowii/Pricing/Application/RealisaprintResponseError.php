<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

/** Interprets the optional error field returned by Realisaprint JSON endpoints. */
final class RealisaprintResponseError
{
    /** @param array<string, mixed> $response */
    public static function isPresent(array $response): bool
    {
        if (!array_key_exists('error', $response)) {
            return false;
        }

        return !in_array($response['error'], [false, null, ''], true);
    }
}
