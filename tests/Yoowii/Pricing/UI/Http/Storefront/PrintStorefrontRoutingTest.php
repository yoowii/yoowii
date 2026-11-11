<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Router;

final class PrintStorefrontRoutingTest extends KernelTestCase
{
    public function testTheLocalizedConfiguratorAndCartRoutesAreRegistered(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertInstanceOf(Router::class, $router);
        self::assertSame('/fr_FR/print', $router->generate('yoowii_shop_print_catalog', [
            '_locale' => 'fr_FR',
        ]));
        self::assertSame('/fr_FR/print/PRINT_FLYER', $router->generate('yoowii_shop_print_configure', [
            '_locale' => 'fr_FR',
            'productCode' => 'PRINT_FLYER',
        ]));
        self::assertSame('/fr_FR/products/FLYER_STANDARD/print-quote', $router->generate('yoowii_shop_print_product_quote', [
            '_locale' => 'fr_FR',
            'productCode' => 'FLYER_STANDARD',
        ]));
        self::assertSame(
            '/fr_FR/print/quote/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/cart',
            $router->generate('yoowii_shop_print_add_to_cart', [
                '_locale' => 'fr_FR',
                'token' => str_repeat('a', 43),
            ]),
        );
        self::assertSame(
            ['POST'],
            $router->getRouteCollection()->get('yoowii_shop_print_add_to_cart')?->getMethods(),
        );
        self::assertSame(
            ['POST'],
            $router->getRouteCollection()->get('yoowii_shop_print_product_quote')?->getMethods(),
        );
    }
}
