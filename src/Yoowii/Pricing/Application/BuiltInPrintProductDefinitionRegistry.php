<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;

final readonly class BuiltInPrintProductDefinitionRegistry
{
    /** @var array<string, PrintProductDefinition> */
    private array $definitions;

    public function __construct()
    {
        $flyer = BuiltInPrintProductDefinitions::flyer();
        $businessCard = BuiltInPrintProductDefinitions::businessCard();
        $this->definitions = [
            $flyer->productCode() => $flyer,
            $businessCard->productCode() => $businessCard,
        ];
    }

    /** @return array<string, PrintProductDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->definitions);
    }

    public function get(string $productCode): PrintProductDefinition
    {
        return $this->definitions[$productCode]
            ?? throw new \InvalidArgumentException(sprintf('Unknown print product definition "%s".', $productCode));
    }
}
