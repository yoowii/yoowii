<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

final class RealisaprintDraftData
{
    public string $productCode = '';

    public string $name = '';

    public string $stock = '';

    /** Provider picture selected in the draft form; it is downloaded server-side only on creation. */
    public string $selectedPicture = '';

    /** @var 'classic'|'prescript' */
    public string $configuratorMode = 'classic';

    /** @var string JSON object keyed by canonical Yoowii option code. */
    public string $options = '{}';

    /** @var string JSON array of option codes used by pricing. */
    public string $pricingAxes = '[]';
}
