<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain\Model;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use PHPUnit\Framework\TestCase;

final class RealisaprintCatalogProductTest extends TestCase
{
    public function testItCountsStocksFromTheSynchronizedClassicApiConfiguration(): void
    {
        $product = new RealisaprintCatalogProduct('42', 'Agenda', new \DateTimeImmutable());

        self::assertNull($product->classicStockCount());

        $product->refreshConfiguration(['stocks' => ['837' => 'Papier couché', '1228' => 'Papier recyclé']], new \DateTimeImmutable());

        self::assertSame(2, $product->classicStockCount());
    }

    public function testItReportsNoClassicStockForAConfigurationWithoutStocks(): void
    {
        $product = new RealisaprintCatalogProduct('42', 'Agenda', new \DateTimeImmutable());
        $product->refreshConfiguration(['variables' => []], new \DateTimeImmutable());

        self::assertSame(0, $product->classicStockCount());
    }
}
