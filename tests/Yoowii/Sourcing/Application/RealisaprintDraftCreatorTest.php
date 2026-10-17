<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Sourcing\Application\RealisaprintDraftCreator;
use App\Yoowii\Sourcing\Application\RealisaprintDraftTaxonResolver;
use App\Yoowii\Sourcing\Domain\Model\PrintSupplier;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\Domain\Model\SupplierProduct;
use App\Yoowii\Sourcing\Domain\SupplierIntegrationMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

final class RealisaprintDraftCreatorTest extends TestCase
{
    public function testItRejectsAStockOutsideTheSynchronizedConfiguration(): void
    {
        $catalogProduct = $this->catalogProduct(['837' => 'Agenda']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n’appartient pas à la configuration synchronisée');

        $this->creator()->create(
            $catalogProduct,
            'PRINT_AGENDA',
            'Agenda',
            '1228',
            'classic',
            [],
            ['format'],
        );
    }

    public function testItRejectsDraftCreationWhenNoSynchronizedStockIsAvailable(): void
    {
        $catalogProduct = $this->catalogProduct([]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Synchronisez ou consultez la configuration Realisaprint');

        $this->creator()->create(
            $catalogProduct,
            'PRINT_AGENDA',
            'Agenda',
            '837',
            'classic',
            [],
            ['format'],
        );
    }

    public function testItRejectsPrescriptForAStockNotAdvertisedByThePrescriptCatalogue(): void
    {
        $catalogProduct = $this->catalogProduct(['837' => 'Agenda']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('n’est pas disponible dans l’API Préscript');

        $this->creator()->create(
            $catalogProduct,
            'PRINT_AGENDA',
            'Agenda',
            '837',
            'prescript',
            [],
            ['format'],
        );
    }

    public function testItInitializesADisabledDraftForEveryChannelWithTranslationsAndTaxonomy(): void
    {
        $catalogProduct = $this->catalogProduct(['1229' => 'Agenda mensuel']);
        $catalogProduct->refreshPrescriptStocks(['1229' => 'Agenda mensuel']);
        $supplier = new PrintSupplier('realisaprint', 'Realisaprint', SupplierIntegrationMode::Api);
        $channelFr = new Channel();
        $channelFr->setCode('WEB_FR');
        $channelEn = new Channel();
        $channelEn->setCode('WEB_EN');
        $taxon = $this->taxon('AGENDA_MENSUEL_PERSONNALISE', 'Agenda mensuel personnalisé');

        $productRepository = $this->repository();
        $productRepository->method('findOneBy')->willReturn(null);
        $definitionRepository = $this->repository();
        $definitionRepository->method('findOneBy')->willReturn(null);
        $supplierRepository = $this->repository();
        $supplierRepository->method('findOneBy')->willReturn($supplier);
        $supplierProductRepository = $this->repository();
        $supplierProductRepository->method('findOneBy')->willReturn(null);
        $channelRepository = $this->repository();
        $channelRepository->method('findAll')->willReturn([$channelFr, $channelEn]);
        $repositories = [
            Product::class => $productRepository,
            PersistedPrintProductDefinition::class => $definitionRepository,
            PrintSupplier::class => $supplierRepository,
            SupplierProduct::class => $supplierProductRepository,
            Channel::class => $channelRepository,
        ];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnCallback(static fn (string $class): EntityRepository => $repositories[$class]);
        $entityManager->expects(self::exactly(10))->method('persist');
        $entityManager->expects(self::exactly(2))->method('flush');

        $taxonRepository = $this->repository();
        $taxonRepository->method('findAll')->willReturn([$taxon]);
        $taxonEntityManager = $this->createMock(EntityManagerInterface::class);
        $taxonEntityManager->method('getRepository')->with(Taxon::class)->willReturn($taxonRepository);

        $product = (new RealisaprintDraftCreator($entityManager, new RealisaprintDraftTaxonResolver($taxonEntityManager)))->create(
            $catalogProduct,
            'PRINT_AGENDA_MENSUEL_CHOISI',
            'Agenda mensuel personnalisé',
            '1229',
            'prescript',
            ['format' => ['type' => 'code', 'allowed_values' => ['a4']]],
            ['format'],
        );

        self::assertFalse($product->isEnabled());
        self::assertSame('PRINT_AGENDA_MENSUEL_CHOISI', $product->getCode());
        self::assertSame('PRINT_AGENDA', $product->getPrintDefinitionCode());
        self::assertSame('Agenda mensuel personnalisé', $product->getTranslation('fr_FR')->getName());
        self::assertSame('Agenda mensuel personnalisé', $product->getTranslation('en_US')->getName());
        self::assertSame('agenda-mensuel-personnalise', $product->getTranslation('fr_FR')->getSlug());
        self::assertTrue($product->getTranslations()->containsKey('fr_FR'));
        self::assertTrue($product->getTranslations()->containsKey('en_US'));
        self::assertCount(2, $product->getChannels());
        self::assertSame($taxon, $product->getMainTaxon());
        self::assertTrue($product->getTaxons()->contains($taxon));

        $variant = $product->getVariants()->first();
        self::assertInstanceOf(\App\Entity\Product\ProductVariant::class, $variant);
        self::assertFalse($variant->isEnabled());
        self::assertCount(2, $variant->getChannelPricings());
        foreach ($variant->getChannelPricings() as $pricing) {
            self::assertSame(100, $pricing->getPrice());
            self::assertSame(100, $pricing->getOriginalPrice());
            self::assertSame(100, $pricing->getMinimumPrice());
        }

        $emptyTaxonRepository = $this->repository();
        $emptyTaxonRepository->method('findAll')->willReturn([]);
        $emptyTaxonEntityManager = $this->createMock(EntityManagerInterface::class);
        $emptyTaxonEntityManager->method('getRepository')->with(Taxon::class)->willReturn($emptyTaxonRepository);
        $productWithoutTaxon = (new RealisaprintDraftCreator($entityManager, new RealisaprintDraftTaxonResolver($emptyTaxonEntityManager)))->create(
            $catalogProduct,
            'PRINT_AGENDA_SANS_TAXON',
            'Agenda sans taxon',
            '1229',
            'prescript',
            ['format' => ['type' => 'code', 'allowed_values' => ['a4']]],
            ['format'],
        );

        self::assertNull($productWithoutTaxon->getMainTaxon());
        self::assertCount(0, $productWithoutTaxon->getTaxons());
    }

    /** @param array<int|string, string> $stocks */
    private function catalogProduct(array $stocks): RealisaprintCatalogProduct
    {
        $catalogProduct = new RealisaprintCatalogProduct('42', 'Agenda', new \DateTimeImmutable());
        $catalogProduct->refreshConfiguration(['stocks' => $stocks, 'variables' => []], new \DateTimeImmutable());

        return $catalogProduct;
    }

    private function creator(): RealisaprintDraftCreator
    {
        return new RealisaprintDraftCreator(
            $this->createMock(EntityManagerInterface::class),
            new RealisaprintDraftTaxonResolver($this->createMock(EntityManagerInterface::class)),
        );
    }

    /** @return EntityRepository<object>&MockObject */
    private function repository(): EntityRepository
    {
        return $this->createMock(EntityRepository::class);
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
