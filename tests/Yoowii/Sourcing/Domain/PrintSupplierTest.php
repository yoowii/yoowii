<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain;

use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\SupplierCapability;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use PHPUnit\Framework\TestCase;

final class PrintSupplierTest extends TestCase
{
    public function testItExposesItsIntegrationCapabilities(): void
    {
        $supplier = new PrintSupplier(
            'realisaprint',
            'Realisaprint',
            SupplierIntegrationMode::Api,
            [SupplierCapability::RealtimeQuote, SupplierCapability::OrderSubmission],
        );

        self::assertTrue($supplier->supports(SupplierCapability::RealtimeQuote));
        self::assertTrue($supplier->supports(SupplierCapability::OrderSubmission));
        self::assertFalse($supplier->supports(SupplierCapability::NeutralShipping));
    }

    public function testItRejectsAnInvalidStableCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The supplier code must contain lowercase letters');

        new PrintSupplier('Realisaprint API', 'Realisaprint', SupplierIntegrationMode::Api);
    }
}
