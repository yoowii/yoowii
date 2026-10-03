<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

final class RealisaprintMappingVariableData
{
    public string $providerVariable = '';

    public string $option = '';

    /** JSON object: canonical Yoowii value => Realisaprint value. */
    public string $values = '{}';
}
