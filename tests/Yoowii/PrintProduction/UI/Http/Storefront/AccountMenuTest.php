<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\UI\Http\Storefront;

use Knp\Menu\FactoryInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class AccountMenuTest extends KernelTestCase
{
    public function testPrintJobsEntryFollowsOrderHistory(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $factory = $container->get('knp_menu.factory');
        $dispatcher = $container->get('event_dispatcher');

        self::assertInstanceOf(FactoryInterface::class, $factory);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $menu = $factory->createItem('root');
        $menu->addChild('order_history');
        $dispatcher->dispatch(new MenuBuilderEvent($factory, $menu), 'sylius.menu.shop.account');

        self::assertSame(['order_history', 'yoowii_print_jobs'], array_keys($menu->getChildren()));
        self::assertSame('Mes impressions', $menu->getChild('yoowii_print_jobs')?->getLabel());
        self::assertSame('yoowii_shop_account_print_jobs', $menu->getChild('yoowii_print_jobs')?->getExtra('routes')[0]['route'] ?? null);
    }
}
