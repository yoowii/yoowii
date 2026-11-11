<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link compatible legacy Realisaprint product drafts to their shared catalogue definition.';
    }

    public function up(Schema $schema): void
    {
        // Only consolidate definitions with exactly the same schema. Older
        // stock-specific schemas remain untouched and therefore safe.
        $canonicalCode = "CONCAT('PRINT_REALISAPRINT_', UPPER(TRIM(BOTH '_' FROM REGEXP_REPLACE(supplier_product.code, '[^A-Za-z0-9]+', '_'))))";
        $join = ' INNER JOIN yoowii_supplier_product supplier_product ON supplier_product.id = mapping.supplier_product_id'
            . ' INNER JOIN yoowii_print_supplier supplier ON supplier.id = supplier_product.supplier_id'
            . ' INNER JOIN yoowii_print_product_definition source_definition ON source_definition.id = mapping.print_product_definition_id'
            . ' INNER JOIN yoowii_print_product_definition canonical_definition ON canonical_definition.product_code = ' . $canonicalCode
            . ' AND canonical_definition.options = source_definition.options'
            . ' AND canonical_definition.pricing_axes = source_definition.pricing_axes';

        $this->addSql('UPDATE yoowii_supplier_product_mapping_version mapping' . $join
            . " SET mapping.print_product_definition_id = canonical_definition.id WHERE supplier.code = 'realisaprint'");
        $this->addSql('UPDATE sylius_product product INNER JOIN yoowii_supplier_product_mapping_version mapping ON mapping.yoowii_product_code = product.code' . $join
            . " SET product.print_definition_code = canonical_definition.product_code WHERE supplier.code = 'realisaprint'");
    }

    public function down(Schema $schema): void
    {
        // Data consolidation is intentionally irreversible.
    }
}
