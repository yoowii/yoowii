<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintMappingData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RealisaprintMappingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('version', TextType::class, ['label' => 'Version immuable du mapping'])
            ->add('stock', TextType::class, ['label' => 'Stock Realisaprint'])
            ->add('variables', CollectionType::class, [
                'label' => 'Correspondance des variables',
                'entry_type' => RealisaprintMappingVariableType::class,
                'entry_options' => ['yoowii_options' => $options['yoowii_options']],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RealisaprintMappingData::class, 'yoowii_options' => []]);
        $resolver->setAllowedTypes('yoowii_options', 'array');
    }
}
