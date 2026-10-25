<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\UI\Http\Admin\Data;

use App\Entity\Taxonomy\Taxon;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class RealisaprintDraftData
{
    public string $productCode = '';

    public string $name = '';

    public string $stock = '';

    /** Category assigned as both a product taxon and its main category. */
    public ?Taxon $taxon = null;

    /** Provider picture selected in the draft form; it is downloaded server-side only on creation. */
    public string $selectedPicture = '';

    /** Remote image URL supplied by the administrator. */
    public string $pictureUrl = '';

    /** Image uploaded by the administrator. */
    public ?UploadedFile $pictureFile = null;

    /** @var 'classic'|'prescript' */
    public string $configuratorMode = 'classic';

    /** @var string JSON object keyed by canonical Yoowii option code. */
    public string $options = '{}';

    /** @var string JSON array of option codes used by pricing. */
    public string $pricingAxes = '[]';
}
