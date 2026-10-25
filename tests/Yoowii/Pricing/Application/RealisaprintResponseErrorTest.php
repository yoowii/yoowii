<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Application\RealisaprintResponseError;
use PHPUnit\Framework\TestCase;

final class RealisaprintResponseErrorTest extends TestCase
{
    /** @dataProvider successfulResponses */
    public function testItDoesNotTreatAnExplicitFalseErrorAsAnError(array $response): void
    {
        self::assertFalse(RealisaprintResponseError::isPresent($response));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function successfulResponses(): iterable
    {
        yield 'without error key' => [[]];
        yield 'boolean false' => [['error' => false]];
        yield 'null' => [['error' => null]];
        yield 'empty string' => [['error' => '']];
    }

    public function testItDetectsSupplierErrors(): void
    {
        self::assertTrue(RealisaprintResponseError::isPresent(['error' => 'Configuration invalide']));
    }
}
