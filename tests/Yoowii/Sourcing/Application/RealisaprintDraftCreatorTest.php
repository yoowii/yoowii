<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Application\RealisaprintDraftCreator;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RealisaprintDraftCreatorTest extends TestCase
{
    public function testItRejectsAStockOutsideTheSynchronizedConfiguration(): void
    {
        $catalogProduct = $this->catalogProduct(['837' => 'Agenda']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('n’appartient pas à la configuration synchronisée');

        (new RealisaprintDraftCreator($this->createMock(EntityManagerInterface::class)))->create(
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

        (new RealisaprintDraftCreator($this->createMock(EntityManagerInterface::class)))->create(
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

        (new RealisaprintDraftCreator($this->createMock(EntityManagerInterface::class)))->create(
            $catalogProduct,
            'PRINT_AGENDA',
            'Agenda',
            '837',
            'prescript',
            [],
            ['format'],
        );
    }

    /** @param array<int|string, string> $stocks */
    private function catalogProduct(array $stocks): RealisaprintCatalogProduct
    {
        $catalogProduct = new RealisaprintCatalogProduct('42', 'Agenda', new \DateTimeImmutable());
        $catalogProduct->refreshConfiguration(['stocks' => $stocks, 'variables' => []], new \DateTimeImmutable());

        return $catalogProduct;
    }
}
