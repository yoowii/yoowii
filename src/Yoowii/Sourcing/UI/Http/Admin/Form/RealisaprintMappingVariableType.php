<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintMappingVariableData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RealisaprintMappingVariableType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerVariable', TextType::class, ['label' => 'Variable Realisaprint', 'attr' => ['class' => 'form-control']])
            ->add('option', ChoiceType::class, ['label' => 'Option Yoowii', 'choices' => $options['yoowii_options'], 'attr' => ['class' => 'form-select']])
            ->add('values', TextType::class, [
                'label' => 'Correspondances de valeurs',
                'help' => 'JSON, par exemple {"a5":"2","a6":"3"}.',
                'attr' => ['class' => 'form-control font-monospace sourcing-code-editor'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RealisaprintMappingVariableData::class, 'yoowii_options' => []]);
        $resolver->setAllowedTypes('yoowii_options', 'array');
    }
}
