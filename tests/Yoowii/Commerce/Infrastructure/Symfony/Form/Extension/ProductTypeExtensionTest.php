<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Commerce\Infrastructure\Symfony\Form\Extension;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Commerce\Infrastructure\Symfony\Form\Extension\ProductTypeExtension;
use App\Yoowii\Pricing\Application\BuiltInPrintProductDefinitionRegistry;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\Form\Type\ProductType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtensionTest extends TestCase
{
    public function testItAddsTheFulfillmentTypeFieldToTheAdminProductForm(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::exactly(2))
            ->method('add')
            ->willReturnCallback(static function (string $name, string $type, array $options) use ($builder): FormBuilderInterface {
                if ('fulfillmentType' === $name) {
                    self::assertSame(EnumType::class, $type);
                    self::assertSame(FulfillmentType::class, $options['class']);
                    self::assertSame('yoowii.fulfillment_type.print', $options['choice_label'](FulfillmentType::Print));
                } else {
                    self::assertSame('printDefinitionCode', $name);
                    self::assertSame(ChoiceType::class, $type);
                    self::assertContains('PRINT_FLYER', $options['choices']);
                    self::assertFalse($options['required']);
                }

                return $builder;
            })
        ;

        (new ProductTypeExtension(new BuiltInPrintProductDefinitionRegistry()))->buildForm($builder, []);
    }

    public function testItExtendsTheAdminProductForm(): void
    {
        self::assertSame([ProductType::class], [...ProductTypeExtension::getExtendedTypes()]);
    }
}
