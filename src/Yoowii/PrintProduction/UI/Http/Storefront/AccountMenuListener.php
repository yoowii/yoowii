<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Storefront;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AccountMenuListener
{
    #[AsEventListener(event: 'sylius.menu.shop.account')]
    public function addPrintJobsEntry(MenuBuilderEvent $event): void
    {
        $event->getMenu()
            ->addChild('yoowii_print_jobs', ['route' => 'yoowii_shop_account_print_jobs'])
            ->setLabel('Mes impressions')
            ->setLabelAttribute('icon', 'tabler:printer')
        ;
    }
}
