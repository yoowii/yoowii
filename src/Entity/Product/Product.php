<?php

declare(strict_types=1);

namespace App\Entity\Product;

use App\Yoowii\Commerce\Domain\FulfillmentType;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Product as BaseProduct;
use Sylius\Component\Product\Model\ProductTranslationInterface;
use Sylius\MolliePlugin\Entity\ProductInterface;
use Sylius\MolliePlugin\Entity\ProductTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_product', indexes: [new ORM\Index(name: 'idx_product_print_definition', columns: ['print_definition_code'])])]
class Product extends BaseProduct implements ProductInterface
{
    use ProductTrait;

    #[ORM\Column(name: 'fulfillment_type', length: 32, enumType: FulfillmentType::class, options: ['default' => 'quote_only'])]
    private FulfillmentType $fulfillmentType = FulfillmentType::QuoteOnly;

    #[ORM\Column(name: 'print_definition_code', length: 64, nullable: true)]
    private ?string $printDefinitionCode = null;

    public function getFulfillmentType(): FulfillmentType
    {
        return $this->fulfillmentType;
    }

    public function setFulfillmentType(FulfillmentType $fulfillmentType): void
    {
        $this->fulfillmentType = $fulfillmentType;
    }

    public function getPrintDefinitionCode(): ?string
    {
        return $this->printDefinitionCode;
    }

    public function setPrintDefinitionCode(?string $printDefinitionCode): void
    {
        if (null !== $printDefinitionCode) {
            $printDefinitionCode = trim($printDefinitionCode);
            $printDefinitionCode = '' === $printDefinitionCode ? null : $printDefinitionCode;
        }

        if (null !== $printDefinitionCode && 1 !== preg_match('/^PRINT_[A-Z0-9_]+$/D', $printDefinitionCode)) {
            throw new \InvalidArgumentException('The print definition code is invalid.');
        }

        $this->printDefinitionCode = $printDefinitionCode;
    }

    #[Assert\Callback(groups: ['Default', 'sylius'])]
    public function validatePrintDefinition(ExecutionContextInterface $context): void
    {
        if (FulfillmentType::Print === $this->fulfillmentType && null === $this->printDefinitionCode) {
            $context
                ->buildViolation('yoowii.product.print_definition_required')
                ->atPath('printDefinitionCode')
                ->addViolation()
            ;

            return;
        }

        if (FulfillmentType::Print !== $this->fulfillmentType && null !== $this->printDefinitionCode) {
            $context
                ->buildViolation('yoowii.product.print_definition_forbidden')
                ->atPath('printDefinitionCode')
                ->addViolation()
            ;
        }
    }

    protected function createTranslation(): ProductTranslationInterface
    {
        return new ProductTranslation();
    }
}
