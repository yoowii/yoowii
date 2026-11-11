<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Storefront\Infrastructure\Symfony\Form\Extension;

use App\Yoowii\Storefront\Infrastructure\Symfony\Form\Extension\TaxonTypeExtension;
use PHPUnit\Framework\TestCase;
use Sylius\Bundle\AdminBundle\Form\Type\TaxonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

final class TaxonTypeExtensionTest extends TestCase
{
    public function testItAddsTheYoowiiMainNavigationFields(): void
    {
        $builder = $this->createMock(FormBuilderInterface::class);
        $builder->expects(self::exactly(3))->method('add')->willReturnCallback(
            static function (string $name, string $type, array $options) use ($builder): FormBuilderInterface {
                match ($name) {
                    'showInMainNavigation' => self::assertSame(CheckboxType::class, $type),
                    'mainNavigationPosition' => self::assertSame(IntegerType::class, $type),
                    'mainNavigationLabel' => self::assertSame(TextType::class, $type),
                    default => self::fail(sprintf('Unexpected field "%s".', $name)),
                };
                self::assertArrayHasKey('label', $options);

                return $builder;
            },
        );

        (new TaxonTypeExtension())->buildForm($builder, []);
    }

    public function testItExtendsTheAdminTaxonForm(): void
    {
        self::assertSame([TaxonType::class], [...TaxonTypeExtension::getExtendedTypes()]);
    }
}
