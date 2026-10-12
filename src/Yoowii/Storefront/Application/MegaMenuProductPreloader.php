<?php

declare(strict_types=1);

namespace App\Yoowii\Storefront\Application;

use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;

final class MegaMenuProductPreloader
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Preloads direct products of the second menu level, including their translations and channels.
     * The Twig menu can then render all product links without an additional query per group.
     *
     * @param iterable<TaxonInterface> $taxons First-level children of the channel menu taxon
     */
    public function preload(iterable $taxons): void
    {
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

        $this->entityManager->createQueryBuilder()
            ->select('taxon', 'product', 'productTranslation', 'channel')
            ->from(Taxon::class, 'taxon')
            ->leftJoin('taxon.products', 'product')
            ->leftJoin('product.translations', 'productTranslation')
            ->leftJoin('product.channels', 'channel')
            ->andWhere('taxon.root IN (:roots)')
            ->andWhere('taxon.level = :groupLevel')
            ->setParameter('roots', array_values($roots))
            ->setParameter('groupLevel', $firstLevel + 1)
            ->getQuery()
            ->getResult();
    }
}
