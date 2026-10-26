<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\PrintProduction\Infrastructure\Realisaprint;

use App\Yoowii\PrintProduction\Application\RealisaprintRequestThrottle;
use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
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
            new NullLogger(),
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
            new NullLogger(),
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
            new NullLogger(),
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
        $client = new RealisaprintClient($http, $throttle, 'shop', 'key', true, 'https://api.example.test', 10, 30, new NullLogger());

        self::assertSame([], $client->post('configurations', ['product' => '42'])['stocks']);
        self::assertSame(['acquire', 'request', 'complete'], $events);
    }

    public function testItLogsASanitizedSupplierRejection(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string|\Stringable, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }
        };
        $throttle = $this->createMock(RealisaprintRequestThrottle::class);
        $throttle->method('acquire')->willReturn('lease');
        $http = new MockHttpClient(new MockResponse('{"error":"Variable requise","code":""}'));
        $client = new RealisaprintClient($http, $throttle, 'shop', 'key', true, 'https://api.example.test', 10, 30, $logger);

        $client->post('save_configuration', ['product' => '252', 'stock' => '874', 'variables' => ['VARTICLE_1_' => '50']]);

        self::assertSame('warning', $logger->records[0]['level']);
        self::assertSame('Realisaprint API rejected a request.', $logger->records[0]['message']);
        self::assertSame('Variable requise', $logger->records[0]['context']['supplier_error']['message']);
        self::assertSame('252', $logger->records[0]['context']['request']['product']);
        self::assertArrayNotHasKey('value', $logger->records[0]['context']['request']['variables']['VARTICLE_1_']);
    }
}
