<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Application\Quote\Exception;

final class PrintQuoteUnavailable extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Ce devis est expiré ou a déjà été utilisé. Veuillez recalculer le prix.');
    }
}
