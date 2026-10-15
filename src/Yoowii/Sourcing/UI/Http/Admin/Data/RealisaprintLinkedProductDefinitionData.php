<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

final class RealisaprintLinkedProductDefinitionData
{
    /** @var 'classic'|'prescript' */
    public string $configuratorMode = 'classic';

    /** @var string JSON object keyed by canonical Yoowii option code. */
    public string $options = '{}';

    /** @var string JSON array of option codes used by pricing. */
    public string $pricingAxes = '[]';

    /** @var string JSON profile used only by the Prescript storefront summary. */
    public string $prescriptSummaryProfile = "{\n  \"version\": \"v1\",\n  \"fields\": []\n}";
}
