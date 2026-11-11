<?php

declare(strict_types=1);

namespace App\Tests\Entity\Product;

use App\Entity\Product\Product;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ProductPrintDefinitionValidationTest extends KernelTestCase
{
    public function testAPrintProductRequiresACalculatorDefinition(): void
    {
        self::bootKernel();
        $product = new Product();
        $product->setFulfillmentType(FulfillmentType::Print);

        $violations = self::validator()->validate($product, null, ['sylius']);

        self::assertTrue($this->containsPropertyViolation($violations, 'printDefinitionCode'));
    }

    public function testANonPrintProductRejectsACalculatorDefinition(): void
    {
        self::bootKernel();
        $product = new Product();
        $product->setPrintDefinitionCode('PRINT_FLYER');

        $violations = self::validator()->validate($product);

        self::assertTrue($this->containsPropertyViolation($violations, 'printDefinitionCode'));
    }

    private static function validator(): ValidatorInterface
    {
        $validator = self::getContainer()->get('validator');
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator;
    }

    private function containsPropertyViolation(ConstraintViolationListInterface $violations, string $propertyPath): bool
    {
        foreach ($violations as $violation) {
            if ($propertyPath === $violation->getPropertyPath()) {
                return true;
            }
        }

        return false;
    }
}
