<?php

declare(strict_types=1);

namespace App\Entity\Taxonomy;

use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonInterface;
use CylleneDigital\SyliusAdvancedTaxonPlugin\Entity\AdvancedTaxonTrait;
use Doctrine\ORM\Mapping as ORM;
use Sylius\Component\Core\Model\Taxon as BaseTaxon;
use Sylius\Component\Taxonomy\Model\TaxonTranslationInterface;

#[ORM\Entity]
#[ORM\Table(name: 'sylius_taxon')]
class Taxon extends BaseTaxon implements AdvancedTaxonInterface
{
    use AdvancedTaxonTrait;

    #[ORM\Column(name: 'show_in_main_navigation', type: 'boolean', options: ['default' => false])]
    private bool $showInMainNavigation = false;

    #[ORM\Column(name: 'main_navigation_position', type: 'integer', nullable: true)]
    private ?int $mainNavigationPosition = null;

    #[ORM\Column(name: 'main_navigation_label', type: 'string', length: 255, nullable: true)]
    private ?string $mainNavigationLabel = null;

    public function __construct()
    {
        parent::__construct();

        $this->initializeAdvancedTaxon();
    }

    protected function createTranslation(): TaxonTranslationInterface
    {
        return new TaxonTranslation();
    }

    public function isShowInMainNavigation(): bool
    {
        return $this->showInMainNavigation;
    }

    public function setShowInMainNavigation(bool $showInMainNavigation): void
    {
        $this->showInMainNavigation = $showInMainNavigation;
    }

    public function getMainNavigationPosition(): ?int
    {
        return $this->mainNavigationPosition;
    }

    public function setMainNavigationPosition(?int $mainNavigationPosition): void
    {
        $this->mainNavigationPosition = $mainNavigationPosition;
    }

    public function getMainNavigationLabel(): ?string
    {
        return $this->mainNavigationLabel;
    }

    public function setMainNavigationLabel(?string $mainNavigationLabel): void
    {
        $label = null === $mainNavigationLabel ? null : trim($mainNavigationLabel);
        $this->mainNavigationLabel = '' === $label ? null : $label;
    }
}
