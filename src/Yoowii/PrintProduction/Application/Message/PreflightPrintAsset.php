<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Application\Message;

final readonly class PreflightPrintAsset
{
    public function __construct(public int $assetId, public string $assetChecksum)
    {
    }
}
