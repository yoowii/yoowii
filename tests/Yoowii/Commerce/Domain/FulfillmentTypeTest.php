<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Commerce\Domain;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FulfillmentTypeTest extends TestCase
{
    /** @return iterable<string, array{FulfillmentType, string}> */
    public static function valuesProvider(): iterable
    {
        yield 'print' => [FulfillmentType::Print, 'print'];
        yield 'web project' => [FulfillmentType::WebProject, 'web_project'];
        yield 'media project' => [FulfillmentType::MediaProject, 'media_project'];
        yield 'subscription' => [FulfillmentType::Subscription, 'subscription'];
        yield 'quote only' => [FulfillmentType::QuoteOnly, 'quote_only'];
    }

    #[DataProvider('valuesProvider')]
    public function testItExposesAStablePersistedValue(FulfillmentType $type, string $expectedValue): void
    {
        self::assertSame($expectedValue, $type->value);
        self::assertSame($type, FulfillmentType::from($expectedValue));
    }
}
