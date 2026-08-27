<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Commerce\Infrastructure\Symfony\Form\Extension;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use App\Yoowii\Commerce\Infrastructure\Symfony\Form\Extension\ProductTypeExtension;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\Form\Type\ProductType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

final class ProductTypeExtensionTest extends TestCase
{
    public function testItAddsTheFulfillmentTypeFieldToTheAdminProductForm(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder
            ->expects(self::once())
            ->method('add')
            ->with(
                'fulfillmentType',
                EnumType::class,
                self::callback(static function (array $options): bool {
                    if (
                        !isset($options['class'], $options['label'], $options['choice_label'])
                        || !is_callable($options['choice_label'])
                    ) {
                        return false;
                    }

                    $choiceLabel = $options['choice_label'];

                    return FulfillmentType::class === $options['class']
                        && 'yoowii.form.product.fulfillment_type' === $options['label']
                        && 'yoowii.fulfillment_type.print' === $choiceLabel(FulfillmentType::Print);
                }),
            )
            ->willReturnSelf()
        ;

        (new ProductTypeExtension())->buildForm($builder, []);
    }

    public function testItExtendsTheAdminProductForm(): void
    {
        self::assertSame([ProductType::class], [...ProductTypeExtension::getExtendedTypes()]);
    }
}
