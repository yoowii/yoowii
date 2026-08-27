<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductInterface;
use Sylius\MolliePlugin\Entity\ProductTrait;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product')]
class Product extends BaseProduct implements ProductInterface
{
    use ProductTrait;

    #[ORM\Column(name: 'fulfillment_type', length: 32, enumType: FulfillmentType::class, options: ['default' => 'quote_only'])]
    private FulfillmentType $fulfillmentType = FulfillmentType::QuoteOnly;

    public function getFulfillmentType(): FulfillmentType
    {
        return $this->fulfillmentType;
    }

    public function setFulfillmentType(FulfillmentType $fulfillmentType): void
    {
        $this->fulfillmentType = $fulfillmentType;
    }

    protected function createTranslation(): ProductTranslationInterface
    {
        return new ProductTranslation();
    }
}
