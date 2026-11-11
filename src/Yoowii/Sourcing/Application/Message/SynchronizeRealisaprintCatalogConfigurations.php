<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application\Message;

final readonly class SynchronizeRealisaprintCatalogConfigurations
{
    public function __construct(public int $runId)
    {
    }
}
