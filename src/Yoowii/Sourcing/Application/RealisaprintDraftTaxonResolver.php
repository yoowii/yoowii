<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Entity\Taxonomy\Taxon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

final readonly class RealisaprintDraftTaxonResolver
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function resolve(string $commercialName, string $supplierProductName): ?Taxon
    {
        $taxons = $this->taxons();
        foreach ([$commercialName, $supplierProductName] as $candidate) {
            $matches = $this->matchingTaxons($taxons, $candidate);
            if ([] !== $matches) {
                return $matches[0];
            }
        }

        return $this->pluralSupplierProductTaxon($taxons, $supplierProductName);
    }

    /** @return list<Taxon> */
    private function taxons(): array
    {
        /** @var list<Taxon> $taxons */
        $taxons = $this->entityManager->getRepository(Taxon::class)->findAll();
        foreach ($taxons as $taxon) {
            $taxon->setCurrentLocale('fr_FR');
            $taxon->setFallbackLocale('fr_FR');
        }

        return $taxons;
    }

    /**
     * @param list<Taxon> $taxons
     * @return list<Taxon>
     */
    private function matchingTaxons(array $taxons, string $candidate): array
    {
        $normalized = $this->normalize($candidate);
        if ('' === $normalized) {
            return [];
        }

        $matches = [];
        foreach ($taxons as $taxon) {
            if ($normalized === $this->normalize((string) $taxon->getName())) {
                $matches[] = $taxon;
            }
        }

        usort($matches, static fn (Taxon $left, Taxon $right): int => [
            -strlen((string) $left->getName()),
            (string) $left->getCode(),
        ] <=> [
            -strlen((string) $right->getName()),
            (string) $right->getCode(),
        ]);

        return $matches;
    }

    /** @param list<Taxon> $taxons */
    private function pluralSupplierProductTaxon(array $taxons, string $supplierProductName): ?Taxon
    {
        $supplierProductName = $this->singular($this->normalize($supplierProductName));
        if ('' === $supplierProductName) {
            return null;
        }

        $matches = array_values(array_filter(
            $taxons,
            fn (Taxon $taxon): bool => $supplierProductName === $this->singular($this->normalize((string) $taxon->getName())),
        ));
        if ([] === $matches) {
            return null;
        }

        usort($matches, static fn (Taxon $left, Taxon $right): int => [
            -strlen((string) $left->getName()),
            (string) $left->getCode(),
        ] <=> [
            -strlen((string) $right->getName()),
            (string) $right->getCode(),
        ]);

        return $matches[0];
    }

    private function normalize(string $value): string
    {
        return strtolower(str_replace('-', '', (new AsciiSlugger())->slug($value)->toString()));
    }

    private function singular(string $value): string
    {
        return str_ends_with($value, 's') || str_ends_with($value, 'x') ? substr($value, 0, -1) : $value;
    }
}
