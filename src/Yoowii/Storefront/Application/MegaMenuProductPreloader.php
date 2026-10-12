<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\Application;

use App\Entity\Product\Product;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class MegaMenuProductPreloader
{
    /** @var array<string, list<Product>> */
    private array $productsByTaxonId = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChannelContextInterface $channelContext,
    ) {
    }

    /**
     * Preloads direct products of the second menu level, including their translations and channels.
     * The Twig menu can then render all product links without an additional query per group.
     *
     * @param iterable<TaxonInterface> $taxons First-level children of the channel menu taxon
     */
    public function preload(iterable $taxons): void
    {
        $this->productsByTaxonId = [];
        $roots = [];
        $firstLevel = null;

        foreach ($taxons as $taxon) {
            if (null === $taxon->getId() || null === $taxon->getRoot()) {
                continue;
            }

            $roots[spl_object_id($taxon->getRoot())] = $taxon->getRoot();
            $firstLevel = min($firstLevel ?? PHP_INT_MAX, (int) $taxon->getLevel());
        }

        if ([] === $roots || null === $firstLevel) {
            return;
        }

        /** @var list<Product> $products */
        $products = $this->entityManager->createQueryBuilder()
            ->select('product', 'productTaxon', 'taxon', 'productTranslation', 'channel')
            ->from(Product::class, 'product')
            ->innerJoin('product.productTaxons', 'productTaxon')
            ->innerJoin('productTaxon.taxon', 'taxon')
            ->leftJoin('product.translations', 'productTranslation')
            ->innerJoin('product.channels', 'channel', 'WITH', 'channel = :channel')
            ->andWhere('taxon.root IN (:roots)')
            ->andWhere('taxon.level = :groupLevel')
            ->andWhere('product.enabled = :enabled')
            ->setParameter('roots', array_values($roots))
            ->setParameter('groupLevel', $firstLevel + 1)
            ->setParameter('channel', $this->channelContext->getChannel())
            ->setParameter('enabled', true)
            ->getQuery()
            ->getResult();

        foreach ($products as $product) {
            foreach ($product->getProductTaxons() as $productTaxon) {
                $taxon = $productTaxon->getTaxon();
                $taxonId = $taxon?->getId();

                if (null === $taxonId) {
                    continue;
                }

                $this->productsByTaxonId[(string) $taxonId][] = $product;
            }
        }
    }

    /** @return list<Product> */
    public function productsFor(TaxonInterface $taxon): array
    {
        $taxonId = $taxon->getId();

        return null === $taxonId ? [] : $this->productsByTaxonId[(string) $taxonId] ?? [];
    }
}
