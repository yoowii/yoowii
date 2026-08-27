<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\SupplierRouteData;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SupplierRouteType extends AbstractType
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
            ->add('priority', IntegerType::class, [
                'label' => 'Priorité',
                'help' => '10 pour le principal, 20 puis 30 pour les secours.',
            ])
            ->add('effectiveFrom', DateTimeType::class, [
                'label' => 'Valide à partir du',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('effectiveUntil', DateTimeType::class, [
                'label' => 'Valide jusqu’au',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('active', CheckboxType::class, [
                'label' => 'Route active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SupplierRouteData::class]);
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
