<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\PrintProduction\Infrastructure\Realisaprint\RealisaprintClient;
use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;

/** Loads the provider's contextual catalogue pictures without persisting anything. */
final readonly class RealisaprintDraftPicturePreview
{
    public function __construct(private RealisaprintClient $client)
    {
    }

    /** @return list<string> */
    public function load(RealisaprintCatalogProduct $catalogProduct, string $stock): array
    {
        $configuration = $catalogProduct->configuration();
        $stocks = is_array($configuration) ? ($configuration['stocks'] ?? null) : null;
        if (!is_array($stocks) || !array_key_exists($stock, $stocks)) {
            throw new \InvalidArgumentException('Le stock Realisaprint sélectionné est invalide.');
        }
        if (!$this->client->isEnabled()) {
            throw new \DomainException('L’API Realisaprint est désactivée.');
        }
        $response = $this->client->post('show_variables', [
            'retry' => 1,
            'stock' => $stock,
            'product' => $catalogProduct->providerProductId(),
            'variables' => $this->defaultVariables($configuration),
        ]);
        if (($response['_http_status'] ?? 200) >= 400 || isset($response['error'])) {
            throw new \DomainException('Realisaprint ne peut pas charger les images pour cette configuration.');
        }

        $pictures = [];
        foreach ($response['pictures'] ?? [] as $picture) {
            if (is_string($picture) && RealisaprintClient::isPictureUrl($picture)) {
                $pictures[$picture] = $picture;
            }
        }

        return array_values($pictures);
    }

    /** @param array<string, mixed>|null $configuration @return array<string, bool|float|int|string> */
    private function defaultVariables(?array $configuration): array
    {
        $variables = is_array($configuration) ? ($configuration['variables'] ?? []) : [];
        if (!is_array($variables)) {
            return [];
        }
        $defaults = [];
        foreach ($variables as $id => $variable) {
            if (!is_string($id) || !is_array($variable)) {
                continue;
            }
            $default = $variable['default'] ?? null;
            if (is_scalar($default) && '' !== trim((string) $default)) {
                $defaults[$id] = $default;
                continue;
            }
            if (is_array($variable['values'] ?? null) && [] !== $variable['values']) {
                $first = array_key_first($variable['values']);
                if (is_string($first) || is_int($first)) {
                    $defaults[$id] = $first;
                }
            }
        }

        return $defaults;
    }
}
