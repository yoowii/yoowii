<?php

declare(strict_types=1);

namespace App\Tests\Yoowii\Sourcing\UI\Http\Admin\Controller;

use App\Yoowii\Pricing\Domain\Print\Definition\PersistedPrintProductDefinition;
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
}
