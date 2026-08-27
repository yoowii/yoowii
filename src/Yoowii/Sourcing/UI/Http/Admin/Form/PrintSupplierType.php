<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\Domain\SupplierCapability;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\PrintSupplierData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PrintSupplierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code stable',
                'disabled' => $options['code_disabled'],
                'help' => 'Minuscules, chiffres, tirets ou underscores. Non modifiable après création.',
            ])
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('integrationMode', ChoiceType::class, [
                'label' => 'Mode d’intégration',
                'choices' => SupplierIntegrationMode::cases(),
                'choice_label' => static fn (SupplierIntegrationMode $mode): string => $mode->value,
            ])
            ->add('capabilities', ChoiceType::class, [
                'label' => 'Capacités vérifiées',
                'choices' => SupplierCapability::cases(),
                'choice_label' => static fn (SupplierCapability $capability): string => $capability->value,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Fournisseur actif',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PrintSupplierData::class,
            'code_disabled' => false,
        ]);
        $resolver->setAllowedTypes('code_disabled', 'bool');
    }
}
