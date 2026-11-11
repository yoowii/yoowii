<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Domain;

enum SupplierCapability: string
{
    case RealtimeQuote = 'realtime_quote';
    case OrderSubmission = 'order_submission';
    case ArtworkUpload = 'artwork_upload';
    case OrderTracking = 'order_tracking';
    case NeutralShipping = 'neutral_shipping';
    case DirectCustomerDelivery = 'direct_customer_delivery';
    case TrackingNumber = 'tracking_number';
}
