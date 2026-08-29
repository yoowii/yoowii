<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\UI\Http\Storefront\Form;

use App\Yoowii\Pricing\UI\Http\Storefront\Form\PrintConfiguratorType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

final class PrintConfiguratorTypeTest extends TestCase
{
    public function testItExposesEveryPricingAxisAsExpandedChoiceCards(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::exactly(2))
            ->method('add')
            ->willReturnCallback(static function (string $name, string $type, array $options) use ($builder): FormBuilderInterface {
                self::assertSame(ChoiceType::class, $type);
                self::assertTrue($options['expanded']);
                self::assertFalse($options['placeholder']);
                self::assertNotEmpty($options['choices']);
                self::assertContains($name, ['format', 'quantity']);

                return $builder;
            })
        ;

        (new PrintConfiguratorType())->buildForm($builder, [
            'option_choices' => [
                'format' => ['a5', 'a4'],
                'quantity' => [500, 1000],
            ],
        ]);
    }
}
