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

        return preg_replace('#<style\b[^>]*>.*?</style\s*>#is', '', $withoutStylesheets ?? $markup) ?? $markup;
    }
}
