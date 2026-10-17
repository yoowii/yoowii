<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\RealisaprintCatalogProduct;

final class RealisaprintConfigurationSelection
{
    public function needsRefresh(RealisaprintCatalogProduct $product): bool
    {
        $configuration = $product->configuration();
        if (null === $configuration) {
            return true;
        }

        return array_key_exists('error', $configuration) || array_key_exists('_malformed_response', $configuration) || (isset($configuration['_http_status']) && (!is_int($configuration['_http_status']) || $configuration['_http_status'] >= 400));
    }
}
