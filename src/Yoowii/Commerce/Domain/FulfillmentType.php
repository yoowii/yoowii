<?php

declare(strict_types=1);

namespace App\Yoowii\Commerce\Domain;

enum FulfillmentType: string
{
    case Print = 'print';
    case WebProject = 'web_project';
    case MediaProject = 'media_project';
    case Subscription = 'subscription';
    case QuoteOnly = 'quote_only';
}
