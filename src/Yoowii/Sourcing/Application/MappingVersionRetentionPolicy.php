<?php

declare(strict_types=1);

namespace App\Yoowii\Sourcing\Application;

use App\Yoowii\Sourcing\Domain\Model\SupplierProductMappingVersion;
use Doctrine\DBAL\Connection;

/** The single deletion decision for obsolete supplier mapping drafts. */
final readonly class MappingVersionRetentionPolicy
{
    public function __construct(private Connection $connection)
    {
    }

    public function canDelete(SupplierProductMappingVersion $mapping, \DateTimeImmutable $now): bool
    {
        if ($mapping->isActive() || null !== $mapping->publishedAt() || $mapping->effectiveFrom() > $now->modify('-90 days')) {
            return false;
        }
        $id = $mapping->id();
        $supplierProductId = $mapping->supplierProduct()->id();
        if (null === $id || null === $supplierProductId) {
            return false;
        }

        return !(bool) $this->connection->fetchOne(
            <<<'SQL'
            SELECT EXISTS(
                SELECT 1 FROM sylius_order_item order_item
                WHERE JSON_UNQUOTE(JSON_EXTRACT(order_item.pricing_snapshot, '$.configuration.product_code')) = :product_code
                  AND JSON_UNQUOTE(JSON_EXTRACT(order_item.pricing_snapshot, '$.configuration.sourcing.supplier_product_code')) = :supplier_product_code
                  AND JSON_UNQUOTE(JSON_EXTRACT(order_item.pricing_snapshot, '$.configuration.sourcing.mapping_version')) = :version
            )
            SQL,
            [
                'product_code' => $mapping->yoowiiProductCode(),
                'supplier_product_code' => $mapping->supplierProduct()->code(),
                'version' => $mapping->version(),
            ],
        );
    }
}
