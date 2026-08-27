<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Domain;

use App\Yoowii\Pricing\Domain\PricingSnapshot;
use PHPUnit\Framework\TestCase;

final class PricingSnapshotTest extends TestCase
{
    public function testItRoundTripsThroughItsPersistedRepresentation(): void
    {
        $snapshot = self::createSnapshot();

        $restoredSnapshot = PricingSnapshot::fromArray($snapshot->toArray());

        self::assertSame($snapshot->toArray(), $restoredSnapshot->toArray());
        self::assertSame(12900, $restoredSnapshot->unitPrice());
    }

    public function testItRejectsABreakdownThatDoesNotMatchTheUnitPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The price breakdown total must match the unit price.');

        new PricingSnapshot(
            'print.flyer',
            '2026-08-01',
            ['format' => 'A5'],
            ['production' => 10000, 'total' => 10000],
            12900,
            'EUR',
            new \DateTimeImmutable('2026-08-27T14:00:00+00:00'),
        );
    }

    public function testItRejectsAnInvalidCurrencyCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The currency code must be a three-letter uppercase ISO code.');

        new PricingSnapshot(
            'print.flyer',
            '2026-08-01',
            ['format' => 'A5'],
            ['total' => 12900],
            12900,
            'eur',
            new \DateTimeImmutable('2026-08-27T14:00:00+00:00'),
        );
    }

    public function testItRejectsANonJsonConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The value at "configuration.callback" is not JSON-compatible.');

        new PricingSnapshot(
            'print.flyer',
            '2026-08-01',
            ['callback' => static fn (): null => null],
            ['total' => 12900],
            12900,
            'EUR',
            new \DateTimeImmutable('2026-08-27T14:00:00+00:00'),
        );
    }

    public function testItRejectsAnUnsupportedPersistedSchema(): void
    {
        $payload = self::createSnapshot()->toArray();
        $payload['schema_version'] = 2;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported pricing snapshot schema version.');

        PricingSnapshot::fromArray($payload);
    }

    private static function createSnapshot(): PricingSnapshot
    {
        return new PricingSnapshot(
            'print.flyer',
            '2026-08-01',
            [
                'format' => 'A5',
                'quantity' => 1000,
                'finishing' => ['lamination' => false],
            ],
            [
                'production' => 9900,
                'design' => 3000,
                'total' => 12900,
            ],
            12900,
            'EUR',
            new \DateTimeImmutable('2026-08-27T14:00:00+00:00'),
        );
    }
}
