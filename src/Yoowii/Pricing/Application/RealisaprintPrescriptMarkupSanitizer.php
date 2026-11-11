<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

/** Removes supplier CSS so Prescript cannot alter the storefront outside its component. */
final class RealisaprintPrescriptMarkupSanitizer
{
    public function sanitize(string $markup): string
    {
        $withoutStylesheets = preg_replace(
            '#<link\b(?=[^>]*\brel\s*=\s*(["\'])[^"\']*\bstylesheet\b[^"\']*\1)[^>]*>#i',
            '',
            $markup,
        );

        $withoutStyles = preg_replace('#<style\b[^>]*>.*?</style\s*>#is', '', $withoutStylesheets ?? $markup) ?? $markup;

        // Prescript's legacy saveConfiguration JavaScript embeds API credentials.
        // Storefront requests are proxied by Yoowii, so those credentials must
        // never reach the browser, including in a cached supplier template.
        return preg_replace(
            [
                '#(\.append\(\s*["\'](?:api_key|apikey)["\']\s*,\s*["\'])[^"\']*#i',
                '#((?:api_key|apikey)\s*[:=]\s*["\'])[^"\']*#i',
            ],
            '$1[redacted]',
            $withoutStyles,
        ) ?? $withoutStyles;
    }
}
