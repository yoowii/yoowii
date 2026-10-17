<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Entity\Taxonomy\Taxon;
use App\Yoowii\Sourcing\Application\RealisaprintDraftTaxonResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

final class RealisaprintDraftTaxonResolverTest extends TestCase
{
    public function testItPrefersTheCommercialNameOverTheSupplierProductName(): void
    {
        $stockTaxon = $this->taxon('AGENDA_SPIRALE', 'Agenda spirale');
        $productTaxon = $this->taxon('AGENDA', 'Agenda');

        self::assertSame($stockTaxon, $this->resolver([$productTaxon, $stockTaxon])->resolve('Agenda spirale', 'Agenda'));
    }

    public function testItUsesTheSupplierProductNameWhenNoStockTaxonExists(): void
    {
        $taxon = $this->taxon('AGENDA', 'Agenda');

        self::assertSame($taxon, $this->resolver([$taxon])->resolve('Stock inconnu', 'Agenda'));
    }

    public function testItUsesThePluralSupplierProductTaxonAsTheCategory(): void
    {
        $taxon = $this->taxon('AGENDAS', 'Agendas');

        self::assertSame($taxon, $this->resolver([$taxon])->resolve('Agenda mensuel', 'Agenda'));
    }

    public function testItPrefersTheMostSpecificNameWhenSeveralTaxonsNormalizeTheSameWay(): void
    {
        $mostSpecific = $this->taxon('AGENDA_SPIRALE_LONG', 'Agenda-spi-rale');
        $other = $this->taxon('AGENDA_SPIRALE', 'Agenda spirale');

        self::assertSame($mostSpecific, $this->resolver([$other, $mostSpecific])->resolve('Agenda spirale', 'Agenda'));
    }

    public function testItNormalizesAccentsApostrophesAndSeparators(): void
    {
        $taxon = $this->taxon('CARTE_ETE', "Carte-d'ete");

        self::assertSame($taxon, $this->resolver([$taxon])->resolve('Carte d’été', 'Carte visite'));
    }

    public function testItReturnsNullWhenNoTaxonMatches(): void
    {
        self::assertNull($this->resolver([$this->taxon('AGENDA', 'Agenda')])->resolve('Flyer', 'Dépliant'));
    }

    /** @param list<Taxon> $taxons */
    private function resolver(array $taxons): RealisaprintDraftTaxonResolver
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn($taxons);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Taxon::class)->willReturn($repository);

        return new RealisaprintDraftTaxonResolver($entityManager);
    }

    private function taxon(string $code, string $name): Taxon
    {
        $taxon = new Taxon();
        $taxon->setCode($code);
        $taxon->setCurrentLocale('fr_FR');
        $taxon->setFallbackLocale('fr_FR');
        $taxon->setName($name);

        return $taxon;
    }
}
