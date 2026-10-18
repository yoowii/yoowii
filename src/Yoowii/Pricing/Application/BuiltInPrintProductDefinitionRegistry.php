<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application;

use App\Yoowii\Pricing\Domain\Print\Definition\BuiltInPrintProductDefinitions;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Pricing\Domain\Print\Definition\PrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintPrintDefinitionCode;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
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

    /**
     * Includes inactive persisted definitions so administration can finish a
     * draft product before its supplier mapping is published. Storefront and
     * quote resolution deliberately keep using active definitions only.
     *
     * @return list<string>
     */
    public function adminCodes(): array
    {
        $codes = array_fill_keys(array_keys($this->definitions), true);
        if (null === $this->entityManager) {
            return array_keys($codes);
        }

        foreach ($this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findBy([], ['id' => 'ASC']) as $persisted) {
            if ($persisted instanceof PersistedPrintProductDefinition) {
                $codes[$persisted->productCode()] = true;
            }
        }

        return array_keys($codes);
    }

    /**
     * Choice labels for the Sylius product form. Legacy unlinked definitions
     * are deliberately omitted: a product must select a built-in calculator or
     * the canonical calculator of a Realisaprint catalogue product.
     *
     * @return array<string, string> code => label
     */
    public function adminChoiceLabels(): array
    {
        $labels = [
            'PRINT_FLYER' => 'Flyers',
            'PRINT_BUSINESS_CARD' => 'Cartes de visite',
        ];
        if (null === $this->entityManager) {
            return $labels;
        }

        $available = [];
        foreach ($this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findBy([], ['id' => 'ASC']) as $definition) {
            if ($definition instanceof PersistedPrintProductDefinition) {
                $available[$definition->productCode()] = true;
            }
        }

        $catalogChoices = [];
        foreach ($this->entityManager->getRepository(RealisaprintCatalogProduct::class)->findAll() as $catalogProduct) {
            if (!$catalogProduct instanceof RealisaprintCatalogProduct) {
                continue;
            }
            $code = RealisaprintPrintDefinitionCode::fromProviderProductId($catalogProduct->providerProductId());
            if (isset($available[$code])) {
                $catalogChoices[$code] = sprintf('%s — Realisaprint', $catalogProduct->name());
            }
        }
        asort($catalogChoices, \SORT_NATURAL | \SORT_FLAG_CASE);

        return $labels + $catalogChoices;
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
        $definition = $this->entityManager->getRepository(PersistedPrintProductDefinition::class)->findOneBy(['productCode' => $productCode, 'active' => true]);

        return $definition instanceof PersistedPrintProductDefinition ? $definition->storefrontSchema() : [];
    }
}
