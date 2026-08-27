<?php

declare(strict_types=1);

namespace App\Tests\Entity\Product;

use App\Entity\Product\Product;
use App\Yoowii\Commerce\Domain\FulfillmentType;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testItDefaultsToQuoteOnly(): void
    {
        $product = new Product();

        self::assertSame(FulfillmentType::QuoteOnly, $product->getFulfillmentType());
    }

    public function testItsFulfillmentTypeCanBeChanged(): void
    {
        $product = new Product();

        $product->setFulfillmentType(FulfillmentType::Print);

        self::assertSame(FulfillmentType::Print, $product->getFulfillmentType());
    }
}
