<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\Application;

use App\Entity\Channel\Channel;
use App\Entity\Product\Product;
use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final class MainNavigationTaxonResolver
{
    private const int MAXIMUM_PRODUCTS_PER_PANEL = 15;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    /** @return list<MainNavigationTaxon> */
    public function resolve(): array
    {
        try {
            $channel = $this->channelContext->getChannel();
        } catch (ChannelNotFoundException) {
            return [];
        }

        if (!$channel instanceof Channel || !$channel->getMenuTaxon() instanceof Taxon) {
            return [];
        }

        $menuTaxon = $channel->getMenuTaxon();

        $root = $menuTaxon->getRoot() ?? $menuTaxon;
        $locale = $this->localeContext->getLocaleCode();
        $ancestorQuery = $this->entityManager->createQueryBuilder()
            ->select('1')
            ->from(Taxon::class, 'ancestor')
            ->where('ancestor.root = :root')
            ->andWhere('ancestor.left <= taxon.left')
            ->andWhere('ancestor.right >= taxon.right')
            ->andWhere('ancestor.enabled = false');

        /** @var list<Taxon> $taxons */
        $taxons = $this->entityManager->createQueryBuilder()
            ->select('taxon', 'translation', 'parent', 'parentTranslation')
            ->from(Taxon::class, 'taxon')
            ->innerJoin('taxon.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->leftJoin('taxon.parent', 'parent')
            ->leftJoin('parent.translations', 'parentTranslation', 'WITH', 'parentTranslation.locale = :locale')
            ->where('taxon.root = :root')
            ->andWhere('taxon.showInMainNavigation = true')
            ->andWhere('taxon.enabled = true')
            ->andWhere(sprintf('NOT EXISTS (%s)', $ancestorQuery->getDQL()))
            ->addOrderBy('CASE WHEN taxon.mainNavigationPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('taxon.mainNavigationPosition', 'ASC')
            ->addOrderBy('taxon.position', 'ASC')
            ->addOrderBy('taxon.level', 'ASC')
            ->addOrderBy('translation.name', 'ASC')
            ->addOrderBy('taxon.id', 'ASC')
            ->setParameter('root', $root)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();

        if ($taxons === []) {
            return [];
        }

        $childrenByParent = $this->childrenByParent($taxons, $locale);
        $productsByTaxon = $this->productsByTaxon($taxons, $locale);
        $navigation = [];

        foreach ($taxons as $taxon) {
            $id = $taxon->getId();
            $slug = $taxon->getSlug();
            $name = $taxon->getName();
            if (!is_int($id) || !is_string($slug) || !is_string($name)) {
                continue;
            }

            $parent = $taxon->getParent();
            $parentLink = $parent instanceof Taxon ? $this->link($parent, $locale) : null;
            $children = $childrenByParent[$id] ?? [];
            $products = $productsByTaxon[$id] ?? [];
            $hasProducts = $products !== [];
            $label = $taxon->getMainNavigationLabel();

            $navigation[] = new MainNavigationTaxon(
                $id,
                null !== $label && '' !== $label ? $label : $name,
                $slug,
                $locale,
                (int) $taxon->getLevel(),
                $children !== [],
                $hasProducts,
                count($products) > self::MAXIMUM_PRODUCTS_PER_PANEL,
                $children !== [] || $hasProducts,
                $parentLink,
                $children,
                array_slice($products, 0, self::MAXIMUM_PRODUCTS_PER_PANEL),
            );
        }

        return $navigation;
    }

    /**
     * @param list<Taxon> $taxons
     *
     * @return array<int, list<MainNavigationTaxonLink>>
     */
    private function childrenByParent(array $taxons, string $locale): array
    {
        /** @var list<Taxon> $children */
        $children = $this->entityManager->createQueryBuilder()
            ->select('child', 'translation')
            ->from(Taxon::class, 'child')
            ->innerJoin('child.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->where('child.parent IN (:parents)')
            ->andWhere('child.enabled = true')
            ->addOrderBy('child.position', 'ASC')
            ->addOrderBy('translation.name', 'ASC')
            ->addOrderBy('child.id', 'ASC')
            ->setParameter('parents', $taxons)
            ->setParameter('locale', $locale)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($children as $child) {
            $parent = $child->getParent();
            $parentId = $parent?->getId();
            if (!is_int($parentId)) {
                continue;
            }
            $link = $this->link($child, $locale);
            if (null !== $link) {
                $result[$parentId][] = $link;
            }
        }

        return $result;
    }

    /**
     * @param list<Taxon> $taxons
     *
     * @return array<int, list<Product>>
     */
    private function productsByTaxon(array $taxons, string $locale): array
    {
        /** @var list<Product> $products */
        $products = $this->entityManager->createQueryBuilder()
            ->select('product', 'productTaxon', 'taxon', 'translation', 'channel', 'image')
            ->from(Product::class, 'product')
            ->innerJoin('product.productTaxons', 'productTaxon')
            ->innerJoin('productTaxon.taxon', 'taxon')
            ->innerJoin('product.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->innerJoin('product.channels', 'channel', 'WITH', 'channel = :channel')
            ->leftJoin('product.images', 'image')
            ->where('taxon IN (:taxons)')
            ->andWhere('product.enabled = true')
            ->addOrderBy('productTaxon.position', 'ASC')
            ->addOrderBy('product.id', 'ASC')
            ->setParameter('taxons', $taxons)
            ->setParameter('locale', $locale)
            ->setParameter('channel', $this->channelContext->getChannel())
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($products as $product) {
            foreach ($product->getProductTaxons() as $productTaxon) {
                $taxon = $productTaxon->getTaxon();
                $taxonId = $taxon?->getId();
                if (is_int($taxonId)) {
                    $result[$taxonId][] = $product;
                }
            }
        }

        return $result;
    }

    private function link(Taxon $taxon, string $locale): ?MainNavigationTaxonLink
    {
        $id = $taxon->getId();
        $name = $taxon->getName();
        $slug = $taxon->getSlug();

        return is_int($id) && is_string($name) && is_string($slug)
            ? new MainNavigationTaxonLink($id, $name, $slug, $locale)
            : null;
    }
}
