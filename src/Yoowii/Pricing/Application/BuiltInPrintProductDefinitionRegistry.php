<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BuiltInPrintProductDefinitionRegistry
{
    /** @var array<string, PrintProductDefinition> */
    private array $definitions;

    public function __construct(private ?EntityManagerInterface $entityManager = null)
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
        $definitions = $this->definitions;
        if (null === $this->entityManager) {
            return $definitions;
        }
        foreach ($this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findBy(['active' => true]) as $persisted) {
            if ($persisted instanceof PersistedPrintProductDefinition) {
                $definitions[$persisted->productCode()] = $persisted->definition();
            }
        }

        return $definitions;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function get(string $productCode): PrintProductDefinition
    {
        $definitions = $this->all();

        return $definitions[$productCode]
            ?? throw new \InvalidArgumentException(sprintf('Unknown print product definition "%s".', $productCode));
    }

    /** @return list<array{code: string, label: string, type: string, values: array<string, string>, area: int, position: int, readonly: bool, default: string|int|null}> */
    public function storefrontSchema(string $productCode): array
    {
        if (null === $this->entityManager) {
            return [];
        }
        $definition = $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $productCode]);

        return $definition instanceof PersistedPrintProductDefinition ? $definition->storefrontSchema() : [];
    }
}
