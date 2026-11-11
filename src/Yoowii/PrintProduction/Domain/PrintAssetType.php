<?php

declare(strict_types=1);

namespace App\Yoowii\PrintProduction\Domain;

enum PrintAssetType: string
{
    case CustomerArtwork = 'customer_artwork';
    case Bat = 'bat';
}
