<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Quote;

enum QuoteSource: string
{
    case RealisaprintApi = 'realisaprint_api';
    case RealisaprintCache = 'realisaprint_cache';
    case MatrixFallback = 'matrix_fallback';
}
