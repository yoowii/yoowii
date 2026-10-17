<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Application\RealisaprintRequestThrottle;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RealisaprintClientTest extends TestCase
{
    public function testItBuildsACopyableDiagnosticCurlWithoutExposingCredentials(): void
    {
        $client = new RealisaprintClient(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(RealisaprintRequestThrottle::class),
            'shop-123',
            'private-api-key',
            true,
            'https://api.example.test/',
            10,
            30,
        );

        $command = $client->diagnosticCurl('show_variables', [
            'product' => 'booklet',
            'variables' => ['FORMAT' => 'A4'],
        ]);

        self::assertStringContainsString("curl --request POST 'https://api.example.test/show_variables'", $command);
        self::assertStringContainsString('shop_id=${YOOWII_REALISAPRINT_SHOP_ID}', $command);
        self::assertStringContainsString('api_key=${YOOWII_REALISAPRINT_API_KEY}', $command);
        self::assertStringContainsString("'product=booklet'", $command);
        self::assertStringContainsString("'variables[FORMAT]=A4'", $command);
        self::assertStringNotContainsString('private-api-key', $command);
    }

    public function testDisabledClientDoesNotReserveAProviderCall(): void
    {
        $throttle = $this->createMock(RealisaprintRequestThrottle::class);
        $throttle->expects(self::never())->method('acquire');
        $client = new RealisaprintClient(
            $this->createMock(HttpClientInterface::class),
            $throttle,
            'shop-123',
            'private-api-key',
            false,
            'https://api.example.test/',
            10,
            30,
        );

        self::assertSame(['simulation' => true, 'operation' => 'show_variables', 'payload' => []], $client->post('show_variables', []));
    }

    public function testItRejectsAnInconsistentHttpTimeoutConfiguration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RealisaprintClient(
            $this->createMock(HttpClientInterface::class),
            $this->createMock(RealisaprintRequestThrottle::class),
            'shop-123',
            'private-api-key',
            true,
            'https://api.example.test/',
            10,
            9,
        );
    }

    public function testItCompletesTheGlobalLeaseAfterTheResponseWasRead(): void
    {
        $events = [];
        $throttle = $this->createMock(RealisaprintRequestThrottle::class);
        $throttle->expects(self::once())->method('acquire')->willReturnCallback(static function () use (&$events): string {
            $events[] = 'acquire';

            return 'lease';
        });
        $throttle->expects(self::once())->method('complete')->with('lease')->willReturnCallback(static function () use (&$events): void { $events[] = 'complete'; });
        $http = new MockHttpClient(static function () use (&$events): MockResponse {
            $events[] = 'request';

            return new MockResponse('{"stocks":[]}');
        });
        $client = new RealisaprintClient($http, $throttle, 'shop', 'key', true, 'https://api.example.test', 10, 30);

        self::assertSame([], $client->post('configurations', ['product' => '42'])['stocks']);
        self::assertSame(['acquire', 'request', 'complete'], $events);
    }
}
