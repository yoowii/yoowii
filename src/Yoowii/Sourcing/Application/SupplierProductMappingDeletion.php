<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\ORM\EntityManagerInterface;

/** Deletes only superseded, unused mapping versions. */
final class SupplierProductMappingDeletion
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function delete(SupplierProductMappingVersion $mapping): void
    {
        if ($mapping->isActive()) {
            throw new MappingDeletionRefused('Ce mapping est encore actif : désactivez-le avant de le supprimer.');
        }

        $id = $mapping->id();
        $supplierProductId = $mapping->supplierProduct()->id();
        if (null === $id || null === $supplierProductId) {
            throw new \LogicException('Only persisted mappings can be deleted.');
        }

        /** @var array{has_newer: int|string, has_route: int|string, has_validation: int|string, has_order: int|string}|false $references */
        $references = $this->entityManager->getConnection()->fetchAssociative(
            <<<'SQL'
            SELECT
                EXISTS(SELECT 1 FROM yoowii_supplier_product_mapping_version newer WHERE newer.supplier_product_id = :supplier_product_id AND newer.yoowii_product_code = :product_code AND (newer.effective_from > :effective_from OR (newer.effective_from = :effective_from AND newer.id > :mapping_id))) AS has_newer,
                EXISTS(SELECT 1 FROM yoowii_supplier_route route WHERE route.supplier_product_id = :supplier_product_id AND route.yoowii_product_code = :product_code) AS has_route,
                EXISTS(SELECT 1 FROM yoowii_realisaprint_mapping_validation validation WHERE validation.mapping_id = :mapping_id) AS has_validation,
                EXISTS(SELECT 1 FROM sylius_order_item order_item WHERE JSON_UNQUOTE(JSON_EXTRACT(order_item.pricing_snapshot, '$.configuration.product_code')) = :product_code AND JSON_UNQUOTE(JSON_EXTRACT(order_item.pricing_snapshot, '$.configuration.sourcing.supplier_product_code')) = :supplier_product_code AND JSON_UNQUOTE(JSON_EXTRACT(order_item.pricing_snapshot, '$.configuration.sourcing.mapping_version')) = :version) AS has_order
            SQL,
            [
                'mapping_id' => $id,
                'supplier_product_id' => $supplierProductId,
                'product_code' => $mapping->yoowiiProductCode(),
                'supplier_product_code' => $mapping->supplierProduct()->code(),
                'version' => $mapping->version(),
                'effective_from' => $mapping->effectiveFrom()->format('Y-m-d H:i:s'),
            ],
        );

        if (false === $references || 1 !== (int) $references['has_newer']) {
            throw new MappingDeletionRefused('Ce mapping est la dernière version de ce produit et doit être conservé.');
        }
        if (1 === (int) $references['has_route']) {
            throw new MappingDeletionRefused('Ce mapping est encore utilisé par une route fournisseur et ne peut pas être supprimé.');
        }
        if (1 === (int) $references['has_validation']) {
            throw new MappingDeletionRefused('Ce mapping possède une validation à conserver et ne peut pas être supprimé.');
        }
        if (1 === (int) $references['has_order']) {
            throw new MappingDeletionRefused('Ce mapping est référencé par une commande et ne peut pas être supprimé.');
        }

        $this->entityManager->remove($mapping);
        $this->entityManager->flush();
    }
}
