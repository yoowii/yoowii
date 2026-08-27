<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class AdminMenuListener
{
    #[AsEventListener(event: 'sylius.menu.admin.main')]
    public function addSourcingEntry(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();
        $parent = $menu->getChild('configuration') ?? $menu;
        $parent
            ->addChild('yoowii_print_sourcing', ['route' => 'yoowii_admin_sourcing_dashboard'])
            ->setLabel('Sourcing print')
            ->setLabelAttribute('icon', 'tabler:printer');
    }
}
