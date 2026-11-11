<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Flyer;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Pricing\Domain\Print\PrintConfiguration;

final readonly class FlyerConfiguration
{
    public function __construct(
        private string $format,
        private string $sides,
        private string $paper,
        private int $grammage,
        private int $quantity,
        private string $finishing,
    ) {
        self::assertCode($this->format, 'format');
        self::assertCode($this->paper, 'paper');
        self::assertCode($this->finishing, 'finishing');

        if (!in_array($this->sides, ['one_sided', 'two_sided'], true)) {
            throw new \InvalidArgumentException('Flyer sides must be "one_sided" or "two_sided".');
        }

        if ($this->grammage < 1) {
            throw new \InvalidArgumentException('Flyer grammage must be greater than zero.');
        }

        if ($this->quantity < 1) {
            throw new \InvalidArgumentException('Flyer quantity must be greater than zero.');
        }
    }

    /**
     * @return array{
     *     format: string,
     *     sides: string,
     *     paper: string,
     *     grammage: int,
     *     quantity: int,
     *     finishing: string
     * }
     */
    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'sides' => $this->sides,
            'paper' => $this->paper,
            'grammage' => $this->grammage,
            'quantity' => $this->quantity,
            'finishing' => $this->finishing,
        ];
    }

    public function matrixKey(): string
    {
        return implode('|', [
            $this->format,
            $this->sides,
            $this->paper,
            (string) $this->grammage,
            (string) $this->quantity,
            $this->finishing,
        ]);
    }

    public function asPrintConfiguration(): PrintConfiguration
    {
        return BuiltInPrintProductDefinitions::flyer()->configure($this->toArray());
    }

    private static function assertCode(string $code, string $field): void
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $code)) {
            throw new \InvalidArgumentException(sprintf(
                'Flyer %s must be a canonical lowercase code.',
                $field,
            ));
        }
    }
}
