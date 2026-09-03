<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\UI\Http\Storefront;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Router;

final class PrintFlowRoutingTest extends KernelTestCase
{
    public function testStorefrontUploadAndBatRoutesAreRegisteredWithSafeMethods(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertInstanceOf(Router::class, $router);
        self::assertSame('/fr_FR/flow/print-jobs/PJ-TEST-1', $router->generate('yoowii_shop_flow_print_job_show', [
            '_locale' => 'fr_FR',
            'reference' => 'PJ-TEST-1',
        ]));
        self::assertSame(['POST'], $router->getRouteCollection()->get('yoowii_shop_flow_print_job_upload')?->getMethods());
        self::assertSame(['POST'], $router->getRouteCollection()->get('yoowii_shop_flow_print_job_approve_bat')?->getMethods());
        self::assertSame(['POST'], $router->getRouteCollection()->get('yoowii_shop_flow_print_job_reject_bat')?->getMethods());
        self::assertSame(['GET'], $router->getRouteCollection()->get('yoowii_shop_flow_print_job_download')?->getMethods());
    }

    public function testOrderFileHubUsesTheOpaqueOrderToken(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertInstanceOf(Router::class, $router);
        self::assertSame('/fr_FR/flow/orders/opaque-order-token/print-files', $router->generate('yoowii_shop_flow_order_print_files', [
            '_locale' => 'fr_FR',
            'tokenValue' => 'opaque-order-token',
        ]));
    }

    public function testCustomerPrintJobHubIsProtectedByTheAccountFirewall(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertInstanceOf(Router::class, $router);
        self::assertSame('/fr_FR/account/print-jobs', $router->generate('yoowii_shop_account_print_jobs', ['_locale' => 'fr_FR']));
    }

    public function testPreflightCorrectionCanOnlyBeRequestedByPost(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');

        self::assertInstanceOf(Router::class, $router);
        $route = $router->getRouteCollection()->get('yoowii_admin_print_production_request_preflight_correction');
        self::assertSame(['POST'], $route?->getMethods());
        self::assertSame('/admin/print-production/jobs/12/assets/34/preflight/correction', $router->generate('yoowii_admin_print_production_request_preflight_correction', ['id' => 12, 'assetId' => 34]));
    }
}
