<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin\Controller;

use PHPUnit\Framework\TestCase;

final class RealisaprintPrescriptMappingValidationTemplateTest extends TestCase
{
    public function testItExplainsThatClassicValidationDoesNotApplyToPrescript(): void
    {
        $template = file_get_contents(dirname(__DIR__, 7) . '/templates/admin/sourcing/realisaprint_prescript_mapping_validation.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('show_variables', $template);
        self::assertStringContainsString('cotation classique', $template);
        self::assertStringContainsString('Disponibilité Préscript', $template);
    }
}
