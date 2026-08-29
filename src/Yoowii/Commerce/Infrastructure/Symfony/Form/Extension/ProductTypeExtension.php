<?php

declare(strict_types=1);

namespace App\Yoowii\Commerce\Infrastructure\Symfony\Form\Extension;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use Sylius\Bundle\AdminBundle\Form\Type\ProductType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly BuiltInPrintProductDefinitionRegistry $printDefinitions)
    {
    }

    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $printDefinitionChoices = [];

        foreach ($this->printDefinitions->codes() as $code) {
            $printDefinitionChoices[$code] = $code;
        }

        $builder->add('fulfillmentType', EnumType::class, [
            'class' => FulfillmentType::class,
            'choice_label' => static fn (FulfillmentType $type): string => sprintf('yoowii.fulfillment_type.%s', $type->value),
            'label' => 'yoowii.form.product.fulfillment_type',
        ]);

        $builder->add('printDefinitionCode', ChoiceType::class, [
            'choices' => $printDefinitionChoices,
            'choice_label' => static fn (string $code): string => sprintf('yoowii.print_definition.%s', $code),
            'label' => 'yoowii.form.product.print_definition',
            'help' => 'yoowii.form.product.print_definition_help',
            'placeholder' => 'yoowii.form.product.print_definition_placeholder',
            'required' => false,
        ]);
    }

    /** @return iterable<class-string> */
    public static function getExtendedTypes(): iterable
    {
        return [ProductType::class];
    }
}
