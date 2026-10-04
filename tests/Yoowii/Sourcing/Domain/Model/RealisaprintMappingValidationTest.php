<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Domain\Model;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintMappingValidation;
use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RealisaprintMappingValidationTest extends TestCase
{
    public function testItKeepsTheSupplierBaseAndOptionsCosts(): void
    {
        $validation = new RealisaprintMappingValidation($this->createMock(SupplierProductMappingVersion::class), true, true, 11000, 10000, 1000, ['quantity' => 1], str_repeat('a', 64), [], null, new DateTimeImmutable('2026-10-04T12:35:00+00:00'));

        self::assertSame(11000, $validation->supplierCost());
        self::assertSame(10000, $validation->supplierBaseCost());
        self::assertSame(1000, $validation->supplierOptionsCost());
    }
}
