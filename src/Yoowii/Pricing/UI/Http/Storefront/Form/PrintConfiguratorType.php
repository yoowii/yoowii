<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class PrintConfiguratorType extends AbstractType
{
    /** @param array<string, mixed> $options */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, list<string|int>> $optionChoices */
        $optionChoices = $options['option_choices'];

        foreach ($optionChoices as $code => $values) {
            $choices = [];

            foreach ($values as $value) {
                $choices[$this->choiceLabel($value)] = $value;
            }

            $builder->add($code, ChoiceType::class, [
                'label' => $this->optionLabel($code),
                'choices' => $choices,
                'expanded' => count($choices) <= 8,
                'placeholder' => 'Choisir',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['option_choices', 'product_code']);
        $resolver->setAllowedTypes('option_choices', 'array');
        $resolver->setAllowedTypes('product_code', 'string');
        $resolver->setDefaults([
            'csrf_token_id' => static function (Options $options): string {
                $productCode = $options['product_code'];

                if (!is_string($productCode)) {
                    throw new \InvalidArgumentException('The product code must be a string.');
                }

                return sprintf('configure_print_%s', $productCode);
            },
        ]);
    }

    private function optionLabel(string $code): string
    {
        return match ($code) {
            'format' => 'Format',
            'sides' => 'Impression',
            'paper' => 'Papier',
            'grammage' => 'Grammage',
            'quantity' => 'Quantité',
            'finishing' => 'Finition',
            'corners' => 'Coins',
            default => ucfirst(str_replace('_', ' ', $code)),
        };
    }

    private function choiceLabel(string|int $value): string
    {
        if (is_int($value)) {
            return number_format($value, 0, ',', ' ');
        }

        return match ($value) {
            'one_sided' => 'Recto',
            'two_sided' => 'Recto-verso',
            'square' => 'Carrés',
            'rounded' => 'Arrondis',
            'none' => 'Sans finition',
            'coated_gloss' => 'Couché brillant',
            'coated_matt' => 'Couché mat',
            'matte_lamination' => 'Pelliculage mat',
            default => ucfirst(str_replace('_', ' ', $value)),
        };
    }
}
