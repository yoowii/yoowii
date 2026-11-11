<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Quote;

enum QuoteFallbackReason: string
{
    case RealisaprintDisabled = 'realisaprint_disabled';
    case QuoteDisabled = 'quote_disabled';
    case SupplierNotEligible = 'supplier_not_eligible';
    case MappingMissing = 'mapping_missing';
    case MappingIncompatible = 'mapping_incompatible';
    case RateLimited = 'rate_limited';
    case ApiTimeout = 'api_timeout';
    case ApiTransportError = 'api_transport_error';
    case ApiResponseInvalid = 'api_response_invalid';
    case ApiRejectedConfiguration = 'api_rejected_configuration';
    case ApiPriceMissing = 'api_price_missing';
    case MatrixUsed = 'matrix_used';
}
