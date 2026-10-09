<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\UI\Http\Storefront\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
        /** @var list<array{code: string, label: string, type: string, values: array<string, string>, area: int, position: int, readonly: bool, default: string|int|null}> $fieldSchemas */
        $fieldSchemas = $options['field_schemas'] ?? [];
        $schemasByCode = [];
        foreach ($fieldSchemas as $schema) {
            $schemasByCode[$schema['code']] = $schema;
        }
        $codes = [] !== $fieldSchemas
            ? array_values(array_unique(array_map(static fn (array $schema): string => $schema['code'], $fieldSchemas)))
            : array_keys($optionChoices);

        foreach ($codes as $code) {
            $values = $optionChoices[$code] ?? [];
            $schema = $schemasByCode[$code] ?? null;
            if ([] === $values) {
                if (!is_array($schema) || !in_array($schema['type'], ['integer', 'float', 'text'], true)) {
                    continue;
                }
            }
            if (is_array($schema) && true === ($schema['fixed'] ?? false)) {
                continue;
            }
            if ([] === $values && is_array($schema) && in_array($schema['type'], ['integer', 'float', 'text'], true)) {
                $type = match ($schema['type']) {
                    'integer' => IntegerType::class,
                    'float' => NumberType::class,
                    default => TextType::class,
                };
                $builder->add($code, $type, [
                    'label' => $schema['label'],
                    'required' => true,
                    'attr' => array_filter([
                        'data-area' => $schema['area'] ?? 1,
                        'data-option-code' => $code,
                        'data-depends-on' => is_array($schema['depends_on'] ?? null) ? ($schema['depends_on']['option'] . ':' . $schema['depends_on']['value']) : null,
                        'min' => $schema['minimum'] ?? null,
                        'max' => $schema['maximum'] ?? null,
                        'step' => 'float' === $schema['type'] ? 'any' : null,
                        'maxlength' => 'text' === $schema['type'] ? 255 : null,
                    ], static fn (mixed $value): bool => null !== $value),
                    'required' => false,
                ]);

                continue;
            }
            $choices = [];

            foreach ($values as $value) {
                $label = is_array($schema) && isset($schema['values'][(string) $value]) ? $schema['values'][(string) $value] : $this->choiceLabel($value);
                $choices[$label] = $value;
            }

            $builder->add($code, ChoiceType::class, [
                'label' => is_array($schema) ? $schema['label'] : $this->optionLabel($code),
                'choices' => $choices,
                'expanded' => count($values) <= 8,
                'attr' => [
                    'data-area' => $schema['area'] ?? 1,
                    'data-option-code' => $code,
                    'data-depends-on' => is_array($schema['depends_on'] ?? null) ? ($schema['depends_on']['option'] . ':' . $schema['depends_on']['value']) : null,
                ],
                'required' => false,
                'placeholder' => count($values) <= 8 ? false : 'Choisissez une option',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'field_schemas' => [],
            'csrf_token_id' => static function (Options $options): string {
                $productCode = $options['product_code'];

                if (!is_string($productCode)) {
                    throw new \InvalidArgumentException('The product code must be a string.');
                }

                return sprintf('configure_print_%s', $productCode);
            },
        ]);
        $resolver->setRequired(['option_choices', 'product_code']);
        $resolver->setAllowedTypes('option_choices', 'array');
        $resolver->setAllowedTypes('product_code', 'string');
        $resolver->setAllowedTypes('field_schemas', 'array');
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
