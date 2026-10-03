<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintDraftData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RealisaprintDraftType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('productCode', TextType::class, [
                'label' => 'Code produit Yoowii',
                'help' => 'Doit commencer par PRINT_. Il devient aussi le code du configurateur.',
            ])
            ->add('name', TextType::class, ['label' => 'Nom commercial'])
            ->add('options', TextareaType::class, [
                'label' => 'Options du configurateur',
                'attr' => ['rows' => 14, 'class' => 'font-monospace'],
                'help' => 'JSON : chaque option contient type (code ou integer), required, allowed_values et éventuellement minimum / maximum.',
            ])
            ->add('pricingAxes', TextareaType::class, [
                'label' => 'Axes de prix',
                'attr' => ['rows' => 3, 'class' => 'font-monospace'],
                'help' => 'JSON : liste non vide des codes d’options qui déterminent la cotation.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RealisaprintDraftData::class]);
    }
}
