<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin\Controller;

use PHPUnit\Framework\TestCase;

final class RealisaprintDraftPicturesTemplateTest extends TestCase
{
    public function testDraftPageProvidesAllThreePictureSources(): void
    {
        $template = file_get_contents(dirname(__DIR__, 7) . '/templates/admin/sourcing/realisaprint_catalog_draft.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('realisaprint-draft-pictures', $template);
        self::assertStringContainsString('Charger les images', $template);
        self::assertStringContainsString('form.selectedPicture', $template);
        self::assertStringContainsString('form.pictureUrl', $template);
        self::assertStringContainsString('form.pictureFile', $template);
        self::assertStringContainsString('clearProviderChoice', $template);
        self::assertStringContainsString('Rétablir les valeurs d’origine', $template);
        self::assertStringContainsString('restore_shared_definition', $template);
    }
}
