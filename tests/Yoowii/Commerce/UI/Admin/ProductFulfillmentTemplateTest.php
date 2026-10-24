<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Commerce\UI\Admin;

use PHPUnit\Framework\TestCase;

final class ProductFulfillmentTemplateTest extends TestCase
{
    public function testPrintDefinitionChoiceExplainsItsDefaultProfileRole(): void
    {
        $template = file_get_contents(dirname(__DIR__, 5) . '/templates/admin/product/form/sections/general/fulfillment_type.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('data-bs-toggle="tooltip"', $template);
        self::assertStringContainsString('yoowii.form.product.print_definition_tooltip', $template);
    }
}
