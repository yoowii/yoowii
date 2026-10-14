<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\PrintQuotePreviewData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PrintQuotePreviewType extends AbstractType
{
    public function __construct(private readonly BuiltInPrintProductDefinitionRegistry $definitions)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = $this->productChoices();
        $builder
            ->add('productCode', ChoiceType::class, [
                'label' => 'Produit Yoowii',
                'choices' => $choices,
                'attr' => ['class' => 'form-select'],
            ])
            ->add('configurationJson', TextareaType::class, [
                'label' => 'Configuration JSON',
                'attr' => ['rows' => 12, 'class' => 'form-control font-monospace sourcing-code-editor'],
            ])
            ->add('markupBasisPoints', IntegerType::class, [
                'label' => 'Majoration en points de base',
                'help' => '3500 représente 35 % du coût fournisseur.',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('minimumMargin', IntegerType::class, ['label' => 'Marge minimale en centimes', 'attr' => ['class' => 'form-control']])
            ->add('handlingFee', IntegerType::class, ['label' => 'Frais de traitement en centimes', 'attr' => ['class' => 'form-control']])
            ->add('currencyCode', TextType::class, ['label' => 'Devise', 'attr' => ['class' => 'form-control']]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PrintQuotePreviewData::class]);
    }

    /** @return array<string, string> */
    private function productChoices(): array
    {
        $choices = [];

        foreach ($this->definitions->codes() as $code) {
            $choices[$code] = $code;
        }

        return $choices;
    }
}
