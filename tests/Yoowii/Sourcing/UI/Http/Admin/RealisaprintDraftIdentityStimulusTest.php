<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin;

use PHPUnit\Framework\TestCase;

final class RealisaprintDraftIdentityStimulusTest extends TestCase
{
    public function testItBuildsTheProductCodeFromTheSelectedStockLabel(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 6) . '/assets/admin/controllers/realisaprint-draft-identity_controller.js');

        self::assertIsString($controller);
        self::assertStringContainsString("split(' — ', 2)", $controller);
        self::assertStringContainsString("replace(/[’']/g, '')", $controller);
        self::assertStringContainsString("normalize('NFD')", $controller);
        self::assertStringContainsString('`${this.baseCodeValue}_${this.codePart(label)}`', $controller);
        self::assertStringContainsString('name.value = label;', $controller);
    }
}
