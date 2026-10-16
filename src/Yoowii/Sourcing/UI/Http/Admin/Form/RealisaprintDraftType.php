<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintDraftData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
                'help' => 'Doit commencer par PRINT_. Il devient aussi le code du configurateur, suivi du stock choisi séparé par « _ ». Les apostrophes sont retirées et les accents normalisés.',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('name', TextType::class, ['label' => 'Nom commercial', 'help' => 'Le libellé du stock sélectionné est repris tel quel.', 'attr' => ['class' => 'form-control']])
            ->add('stock', ChoiceType::class, [
                'label' => 'Stock Realisaprint',
                'choices' => $options['stock_choices'],
                'required' => true,
                'placeholder' => false,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('configuratorMode', ChoiceType::class, [
                'label' => 'Configurateur storefront',
                'choices' => ['API classique Yoowii' => 'classic', 'API Préscript Realisaprint' => 'prescript'],
                'help' => 'Préscript est disponible uniquement pour les stocks signalés compatibles dans le catalogue fournisseur.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('options', TextareaType::class, [
                'label' => 'Options du configurateur',
                'attr' => ['rows' => 14, 'class' => 'form-control font-monospace sourcing-code-editor'],
                'help' => 'JSON : chaque option contient type (code ou integer), required, allowed_values et éventuellement minimum / maximum.',
            ])
            ->add('pricingAxes', TextareaType::class, [
                'label' => 'Axes de prix',
                'attr' => ['rows' => 3, 'class' => 'form-control font-monospace sourcing-code-editor'],
                'help' => 'JSON : liste non vide des codes d’options qui déterminent la cotation.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RealisaprintDraftData::class,
            'stock_choices' => [],
        ]);
        $resolver->setAllowedTypes('stock_choices', 'array');
    }
}
