<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\RealisaprintPrescriptMarkupSanitizer;
use PHPUnit\Framework\TestCase;

final class RealisaprintPrescriptMarkupSanitizerTest extends TestCase
{
    public function testItRemovesAllSupplierStylesWithoutRemovingScriptsOrMarkup(): void
    {
        $markup = '<link rel="stylesheet" href="https://supplier.example/bootstrap.css"><link rel="stylesheet" href="/supplier.css"><style>.supplier { color: red; }</style><script src="/supplier.js"></script><div class="prescript">Configurateur</div>';

        $sanitized = (new RealisaprintPrescriptMarkupSanitizer())->sanitize($markup);

        self::assertStringNotContainsString('bootstrap.css', $sanitized);
        self::assertStringNotContainsString('/supplier.css', $sanitized);
        self::assertStringNotContainsString('.supplier { color: red; }', $sanitized);
        self::assertStringContainsString('/supplier.js', $sanitized);
        self::assertStringContainsString('Configurateur', $sanitized);
    }

    public function testItRedactsSupplierApiCredentialsFromPrescriptScripts(): void
    {
        $markup = "<script>form_data.append('api_key', 'supplier-secret'); const api_key = \"another-secret\";</script>";

        $sanitized = (new RealisaprintPrescriptMarkupSanitizer())->sanitize($markup);

        self::assertStringNotContainsString('supplier-secret', $sanitized);
        self::assertStringNotContainsString('another-secret', $sanitized);
        self::assertStringContainsString('[redacted]', $sanitized);
    }
}
