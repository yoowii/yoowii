<?php

declare(strict_types=1);

namespace App\Yoowii\Commerce\Infrastructure\Symfony\Form\Extension;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use Sylius\Bundle\AdminBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtension extends AbstractTypeExtension
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fulfillmentType', EnumType::class, [
            'class' => FulfillmentType::class,
            'choice_label' => static fn (FulfillmentType $type): string => sprintf('yoowii.fulfillment_type.%s', $type->value),
            'label' => 'yoowii.form.product.fulfillment_type',
        ]);
    }

    /** @return iterable<class-string> */
    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
