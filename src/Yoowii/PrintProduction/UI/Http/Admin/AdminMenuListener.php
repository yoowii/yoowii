<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\UI\Http\Admin;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AdminMenuListener
{
    #[AsEventListener(event: 'sylius.menu.admin.main')]
    public function addPrintProductionEntry(MenuBuilderEvent $event): void
    {
        ($event->getMenu()->getChild('sales') ?? $event->getMenu())
            ->addChild('yoowii_print_production', ['route' => 'yoowii_admin_print_production_index'])
            ->setLabel('Production print')
            ->setLabelAttribute('icon', 'tabler:printer');
    }
}
