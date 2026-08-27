<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Definition;

enum PrintOptionType: string
{
    case Code = 'code';
    case Integer = 'integer';
}
