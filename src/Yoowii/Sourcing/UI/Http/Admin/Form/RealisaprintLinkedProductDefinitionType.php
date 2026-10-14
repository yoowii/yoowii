<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintLinkedProductDefinitionData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RealisaprintLinkedProductDefinitionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $configuratorChoices = ['API classique Yoowii' => 'classic'];
        if ($options['prescript_available']) {
            $configuratorChoices['API Préscript Realisaprint'] = 'prescript';
        }

        $builder
            ->add('configuratorMode', ChoiceType::class, [
                'label' => 'Configurateur storefront',
                'choices' => $configuratorChoices,
                'help' => $options['prescript_available']
                    ? 'Préscript est disponible pour le stock actuellement enregistré.'
                    : 'Préscript n’est pas disponible pour le stock actuellement enregistré.',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('options', TextareaType::class, [
                'label' => 'Configuration technique proposée (avancé)',
                'attr' => ['rows' => 18, 'class' => 'form-control font-monospace sourcing-code-editor'],
                'help' => 'JSON des options du configurateur. Les identifiants provider_variable doivent rester alignés avec le catalogue synchronisé.',
            ])
            ->add('pricingAxes', TextareaType::class, [
                'label' => 'Axes de prix',
                'attr' => ['rows' => 4, 'class' => 'form-control font-monospace sourcing-code-editor'],
                'help' => 'JSON : liste non vide des codes d’options déterminant la cotation.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RealisaprintLinkedProductDefinitionData::class,
            'prescript_available' => false,
        ]);
        $resolver->setAllowedTypes('prescript_available', 'bool');
    }
}
