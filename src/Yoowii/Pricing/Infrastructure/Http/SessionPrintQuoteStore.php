<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Infrastructure\Http;

use App\Yoowii\Pricing\Application\Quote\Exception\PrintQuoteUnavailable;
use App\Yoowii\Pricing\Application\Quote\PrintQuoteStore;
use App\Yoowii\Pricing\Application\Quote\StoredPrintQuote;
use App\Yoowii\Pricing\Domain\PricingSnapshot;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final readonly class SessionPrintQuoteStore implements PrintQuoteStore
{
    private const SESSION_KEY = 'yoowii.print_quotes';
    private const MAX_QUOTES = 10;

    public function __construct(
        private RequestStack $requestStack,
        private int $timeToLive,
    ) {
        if ($this->timeToLive < 60) {
            throw new \InvalidArgumentException('The print quote lifetime must be at least 60 seconds.');
        }
    }

    public function issue(
        string $variantCode,
        PricingSnapshot $pricingSnapshot,
        \DateTimeImmutable $now,
    ): string {
        $storedQuote = new StoredPrintQuote(
            $variantCode,
            $pricingSnapshot,
            $now->modify(sprintf('+%d seconds', $this->timeToLive)),
        );
        $session = $this->session();
        $quotes = $this->activeQuotes($session, $now);
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $quotes[$token] = [
            'variant_code' => $variantCode,
            'pricing_snapshot' => $pricingSnapshot->toArray(),
            'expires_at' => $storedQuote->expiresAt()->format(\DateTimeInterface::ATOM),
        ];

        if (count($quotes) > self::MAX_QUOTES) {
            $quotes = array_slice($quotes, -self::MAX_QUOTES, null, true);
        }

        $session->set(self::SESSION_KEY, $quotes);

        return $token;
    }

    public function find(string $token, \DateTimeImmutable $now): StoredPrintQuote
    {
        $session = $this->session();
        $quotes = $this->activeQuotes($session, $now);
        $session->set(self::SESSION_KEY, $quotes);

        return $this->hydrate($quotes[$token] ?? null);
    }

    public function consume(string $token, \DateTimeImmutable $now): StoredPrintQuote
    {
        $session = $this->session();
        $quotes = $this->activeQuotes($session, $now);
        $quote = $this->hydrate($quotes[$token] ?? null);
        unset($quotes[$token]);
        $session->set(self::SESSION_KEY, $quotes);

        return $quote;
    }

    private function session(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            throw new \LogicException('A session is required to store a print quote.');
        }

        return $request->getSession();
    }

    /**
     * @return array<string, array{variant_code: string, pricing_snapshot: array<string, mixed>, expires_at: string}>
     */
    private function activeQuotes(SessionInterface $session, \DateTimeImmutable $now): array
    {
        $storedQuotes = $session->get(self::SESSION_KEY, []);

        if (!is_array($storedQuotes)) {
            return [];
        }

        $activeQuotes = [];

        foreach ($storedQuotes as $token => $storedQuote) {
            if (!is_string($token) || !is_array($storedQuote)) {
                continue;
            }

            $variantCode = $storedQuote['variant_code'] ?? null;
            $pricingSnapshot = $storedQuote['pricing_snapshot'] ?? null;
            $expiresAt = $storedQuote['expires_at'] ?? null;

            if (!is_string($variantCode) || !is_array($pricingSnapshot) || !is_string($expiresAt)) {
                continue;
            }

            try {
                $expiration = new \DateTimeImmutable($expiresAt);
            } catch (\Exception) {
                continue;
            }

            if ($expiration <= $now) {
                continue;
            }

            /** @var array<string, mixed> $pricingSnapshot */
            $activeQuotes[$token] = [
                'variant_code' => $variantCode,
                'pricing_snapshot' => $pricingSnapshot,
                'expires_at' => $expiresAt,
            ];
        }

        return $activeQuotes;
    }

    /**
     * @param array{variant_code: string, pricing_snapshot: array<string, mixed>, expires_at: string}|null $data
     */
    private function hydrate(?array $data): StoredPrintQuote
    {
        if (null === $data) {
            throw new PrintQuoteUnavailable();
        }

        try {
            return new StoredPrintQuote(
                $data['variant_code'],
                PricingSnapshot::fromArray($data['pricing_snapshot']),
                new \DateTimeImmutable($data['expires_at']),
            );
        } catch (\Exception) {
            throw new PrintQuoteUnavailable();
        }
    }
}
