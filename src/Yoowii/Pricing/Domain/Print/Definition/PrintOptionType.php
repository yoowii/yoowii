<?php

declare(strict_types=1);

namespace App\Yoowii\Pricing\Domain\Print\Definition;

enum PrintOptionType: string
{
    case Code = 'code';
    case Checkbox = 'checkbox';
    case Integer = 'integer';
    case Float = 'float';
    case Text = 'text';
}
