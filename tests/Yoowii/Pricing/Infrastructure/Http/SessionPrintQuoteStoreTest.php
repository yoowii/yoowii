<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Pricing\Infrastructure\Http;

use App\Yoowii\Pricing\Application\Quote\Exception\PrintQuoteUnavailable;
use App\Yoowii\Pricing\Domain\PricingSnapshot;
use App\Yoowii\Pricing\Infrastructure\Http\SessionPrintQuoteStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SessionPrintQuoteStoreTest extends TestCase
{
    public function testAQuoteIsBoundToTheSessionAndCanOnlyBeConsumedOnce(): void
    {
        $store = self::store();
        $now = new \DateTimeImmutable('2026-08-27T12:00:00+00:00');
        $token = $store->issue('PRINT_FLYER', self::snapshot(), $now);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token);
        self::assertSame('PRINT_FLYER', $store->find($token, $now)->variantCode());
        self::assertSame(12900, $store->consume($token, $now)->pricingSnapshot()->unitPrice());

        $this->expectException(PrintQuoteUnavailable::class);
        $store->find($token, $now);
    }

    public function testAnExpiredQuoteIsRejected(): void
    {
        $store = self::store();
        $issuedAt = new \DateTimeImmutable('2026-08-27T12:00:00+00:00');
        $token = $store->issue('PRINT_FLYER', self::snapshot(), $issuedAt);

        $this->expectException(PrintQuoteUnavailable::class);
        $store->find($token, $issuedAt->modify('+15 minutes'));
    }

    public function testItRejectsAQuoteWhoseVariantDoesNotMatchTheCalculatedProduct(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::store()->issue(
            'PRINT_BUSINESS_CARD',
            self::snapshot(),
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        );
    }

    private static function store(): SessionPrintQuoteStore
    {
        $request = Request::create('/fr_FR/print/PRINT_FLYER');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new SessionPrintQuoteStore($requestStack, 900);
    }

    private static function snapshot(): PricingSnapshot
    {
        return new PricingSnapshot(
            'print.matrix_exact',
            'retail-v1@matrix-v1',
            [
                'product_code' => 'PRINT_FLYER',
                'schema_version' => '1',
                'options' => ['format' => 'a5', 'quantity' => 1000],
            ],
            ['total' => 12900],
            12900,
            'EUR',
            new \DateTimeImmutable('2026-08-27T12:00:00+00:00'),
        );
    }
}
