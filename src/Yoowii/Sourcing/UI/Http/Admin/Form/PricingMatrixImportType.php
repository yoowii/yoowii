<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\PricingMatrixImportData;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PricingMatrixImportType extends AbstractType
{
    public function __construct(private readonly BuiltInPrintProductDefinitionRegistry $definitions)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = $this->productChoices();
        $builder
            ->add('productCode', ChoiceType::class, [
                'label' => 'Schéma de produit',
                'choices' => $choices,
            ])
            ->add('supplierProduct', EntityType::class, [
                'class' => SupplierProduct::class,
                'choice_label' => static fn (SupplierProduct $product): string => sprintf(
                    '%s — %s (%s)',
                    $product->supplier()->name(),
                    $product->name(),
                    $product->code(),
                ),
                'query_builder' => static fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('product')
                    ->innerJoin('product.supplier', 'supplier')
                    ->orderBy('supplier.name', 'ASC')
                    ->addOrderBy('product.name', 'ASC'),
                'label' => 'Référence fournisseur',
            ])
            ->add('version', TextType::class, [
                'label' => 'Version immuable',
                'help' => 'Exemple : 2026-09-01. Une version existante ne sera jamais remplacée.',
            ])
            ->add('currencyCode', TextType::class, ['label' => 'Devise'])
            ->add('effectiveFrom', DateTimeType::class, [
                'label' => 'Tarifs applicables à partir du',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('file', FileType::class, [
                'label' => 'Matrice CSV canonique',
                'help' => 'UTF-8, maximum 5 Mo. L’import est annulé entièrement si une ligne est invalide.',
            ])
            ->add('activate', CheckboxType::class, [
                'label' => 'Activer immédiatement après validation',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PricingMatrixImportData::class]);
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
