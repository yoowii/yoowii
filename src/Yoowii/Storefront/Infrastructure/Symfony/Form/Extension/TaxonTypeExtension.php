<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\Infrastructure\Symfony\Form\Extension;

use Sylius\Bundle\AdminBundle\Form\Type\TaxonType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class TaxonTypeExtension extends AbstractTypeExtension
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('showInMainNavigation', CheckboxType::class, [
                'required' => false,
                'label' => 'yoowii.form.taxon.show_in_main_navigation',
                'help' => 'yoowii.form.taxon.main_navigation_help',
            ])
            ->add('mainNavigationPosition', IntegerType::class, [
                'required' => false,
                'label' => 'yoowii.form.taxon.main_navigation_position',
                'attr' => ['min' => 0],
            ])
            ->add('mainNavigationLabel', TextType::class, [
                'required' => false,
                'label' => 'yoowii.form.taxon.main_navigation_label',
            ])
        ;
    }

    /** @return iterable<class-string> */
    public static function getExtendedTypes(): iterable
    {
        return [TaxonType::class];
    }
}
