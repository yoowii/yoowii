<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
use App\Yoowii\Pricing\Application\RealisaprintConfigurationMapper;
use Doctrine\ORM\EntityManagerInterface;
use App\Yoowii\Sourcing\Application\RealisaprintMappingCompleteness;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;
use App\Yoowii\Sourcing\UI\Http\Admin\Controller\RealisaprintDraftController;
use App\Yoowii\Sourcing\UI\Http\Admin\Data\RealisaprintDraftData;
use PHPUnit\Framework\TestCase;

final class RealisaprintDraftControllerTest extends TestCase
{
    public function testNumericSelectLabelsProduceValidCanonicalCodesAndCompleteMapping(): void
    {
        $configuration = [
            'stocks' => ['1073' => 'Carte de visite'],
            'variables' => [
                'VARTICLE_23061_' => ['name' => 'Quantité par lot spécifique', 'type' => 'select', 'values' => ['25' => '25', '50' => '50'], 'default' => '25'],
                'VARTICLE_23002_' => ['name' => 'Quantité (du même visuel)', 'type' => 'float', 'values' => false, 'quantity' => true, 'default' => '1000'],
            ],
        ];
        $catalogProduct = new RealisaprintCatalogProduct('297', 'Carte de visite', new \DateTimeImmutable());
        $catalogProduct->refreshConfiguration($configuration, new \DateTimeImmutable());
        $data = new RealisaprintDraftData();
        (new \ReflectionMethod(RealisaprintDraftController::class, 'prefillConfiguration'))
            ->invoke(new RealisaprintDraftController(), $data, $catalogProduct);

        /** @var array<string, array{type: string, allowed_values: list<string|int>, provider_variable: string, provider_values: array<string, string>, default: string|int|null}> $options */
        $options = json_decode($data->options, true, 512, \JSON_THROW_ON_ERROR);
        /** @var non-empty-list<string> $axes */
        $axes = json_decode($data->pricingAxes, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(['value_25', 'value_50'], $options['quantite_par_lot_specifique']['allowed_values']);
        self::assertSame(['value_25' => '25', 'value_50' => '50'], $options['quantite_par_lot_specifique']['provider_values']);
        self::assertSame('value_25', $options['quantite_par_lot_specifique']['default']);
        self::assertSame([], $options['quantite_du_meme_visuel']['allowed_values']);
        self::assertSame([], $options['quantite_du_meme_visuel']['provider_values']);

        $definition = new PersistedPrintProductDefinition('PRINT_CARTE_DE_VISITE', 'v1', $options, $axes);
        $definition->definition();
        $rules = [];
        foreach ($options as $code => $option) {
            $rules[$option['provider_variable']] = ['option' => $code, 'values' => $option['provider_values']];
        }
        (new RealisaprintMappingCompleteness())->assertComplete($definition, $configuration, ['stock' => '1073', 'variables' => $rules]);
    }

    public function testReadonlyTextDefaultBecomesFixedProviderValue(): void
    {
        $catalog = new RealisaprintCatalogProduct('70', 'Produit', new \DateTimeImmutable());
        $catalog->refreshConfiguration(['stocks' => ['1' => 'Stock'], 'variables' => [
            'VARTICLE_28779_' => ['name' => 'Face imprimée', 'type' => 'text', 'values' => false, 'default' => 'Recto', 'readonly' => true],
            'COMMENTAIRE_' => ['name' => 'Commentaire', 'type' => 'text', 'values' => false, 'readonly' => false],
        ]], new \DateTimeImmutable());
        $data = new RealisaprintDraftData();
        (new \ReflectionMethod(RealisaprintDraftController::class, 'prefillConfiguration'))->invoke(new RealisaprintDraftController(), $data, $catalog);
        $options = json_decode($data->options, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(['Recto'], $options['face_imprimee']['allowed_values']);
        self::assertSame(['Recto' => 'Recto'], $options['face_imprimee']['provider_values']);
        self::assertArrayNotHasKey('fixed_value', $options['commentaire']);

        $definition = new PersistedPrintProductDefinition('PRINT_PRODUIT', 'v1', $options, array_keys($options));
        $configuration = $definition->definition()->configure(['face_imprimee' => 'Recto', 'commentaire' => 'texte']);
        $payload = (new RealisaprintConfigurationMapper($this->createMock(EntityManagerInterface::class)))->mapMapping($configuration, ['realisaprint' => ['product' => '70', 'stock' => '1', 'variables' => [
            'VARTICLE_28779_' => ['option' => 'face_imprimee', 'values' => ['Recto' => 'Recto']],
            'COMMENTAIRE_' => ['option' => 'commentaire', 'values' => []],
        ]]], 'v1');

        self::assertSame('Recto', $payload['variables']['VARTICLE_28779_']);
    }

    public function testReadonlyValueWithoutUsableDefaultIsRejected(): void
    {
        $catalog = new RealisaprintCatalogProduct('70', 'Produit', new \DateTimeImmutable());
        $catalog->refreshConfiguration(['stocks' => ['1' => 'Stock'], 'variables' => ['V' => ['name' => 'Face', 'type' => 'text', 'values' => false, 'readonly' => true]]], new \DateTimeImmutable());
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('valeur par défaut exploitable');
        (new \ReflectionMethod(RealisaprintDraftController::class, 'prefillConfiguration'))->invoke(new RealisaprintDraftController(), new RealisaprintDraftData(), $catalog);
    }

}
