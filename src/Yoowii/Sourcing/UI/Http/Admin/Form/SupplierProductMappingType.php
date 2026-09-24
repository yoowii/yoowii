<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\SupplierProductMappingData;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SupplierProductMappingType extends AbstractType
{
    public function __construct(private readonly BuiltInPrintProductDefinitionRegistry $definitions)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('yoowiiProductCode', ChoiceType::class, ['label' => 'Produit Yoowii', 'choices' => array_combine($this->definitions->codes(), $this->definitions->codes())])
            ->add('supplierProduct', EntityType::class, [
                'class' => SupplierProduct::class,
                'label' => 'Référence fournisseur',
                'choice_label' => static fn (SupplierProduct $product): string => sprintf('%s — %s (%s)', $product->supplier()->name(), $product->name(), $product->code()),
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('product')->innerJoin('product.supplier', 'supplier')->orderBy('supplier.name', 'ASC')->addOrderBy('product.name', 'ASC'),
            ])
            ->add('version', TextType::class, ['label' => 'Version immuable'])
            ->add('effectiveFrom', DateTimeType::class, ['label' => 'Applicable à partir du', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('configurationMapping', TextareaType::class, [
                'label' => 'Mapping JSON',
                'attr' => ['rows' => 18, 'class' => 'font-monospace'],
                'help' => 'Pour Realisaprint, renseigne product, stock et variables. Les clés de values sont les valeurs canoniques Yoowii ; catalog_values est utile pour une quantité sans table values.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SupplierProductMappingData::class]);
    }
}
