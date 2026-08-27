<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Form;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\SupplierProductData;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SupplierProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('supplier', EntityType::class, [
                'class' => PrintSupplier::class,
                'choice_label' => static fn (PrintSupplier $supplier): string => sprintf('%s (%s)', $supplier->name(), $supplier->code()),
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('supplier')->orderBy('supplier.name', 'ASC'),
                'disabled' => $options['identity_disabled'],
                'label' => 'Fournisseur',
            ])
            ->add('code', TextType::class, [
                'label' => 'Référence fournisseur',
                'disabled' => $options['identity_disabled'],
            ])
            ->add('name', TextType::class, ['label' => 'Nom interne'])
            ->add('active', CheckboxType::class, [
                'label' => 'Référence active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupplierProductData::class,
            'identity_disabled' => false,
        ]);
        $resolver->setAllowedTypes('identity_disabled', 'bool');
    }
}
