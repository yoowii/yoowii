<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

final class RealisaprintMappingData
{
    public string $version = 'v1';

    public string $stock = '';

    /** @var list<RealisaprintMappingVariableData> */
    public array $variables = [];
}
