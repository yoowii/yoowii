<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use Symfony\Component\Validator\Constraints as Assert;

final class PrintQuotePreviewData
{
    #[Assert\NotBlank]
    public string $productCode = 'PRINT_FLYER';

    #[Assert\NotBlank]
    public string $configurationJson = <<<'JSON'
{
  "format": "a5",
  "sides": "two_sided",
  "paper": "coated_gloss",
  "grammage": 135,
  "quantity": 1000,
  "finishing": "none"
}
JSON;

    #[Assert\Range(min: 0, max: 100000)]
    public int $markupBasisPoints = 3500;

    #[Assert\PositiveOrZero]
    public int $minimumMargin = 1200;

    #[Assert\PositiveOrZero]
    public int $handlingFee = 0;

    #[Assert\Currency]
    public string $currencyCode = 'EUR';
}
