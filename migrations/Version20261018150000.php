<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce one shared configurator definition for each Realisaprint catalogue product.';
    }

    public function up(Schema $schema): void
    {
        $code = "CONCAT('PRINT_REALISAPRINT_', UPPER(TRIM(BOTH '_' FROM REGEXP_REPLACE(supplier_product.code, '[^A-Za-z0-9]+', '_'))))";

        // Seed a canonical definition from the most recent mapping when a
        // catalogue product predates the shared-definition convention.
        $this->addSql('INSERT INTO yoowii_print_product_definition (product_code, schema_version, options, pricing_axes, active) '
            . 'SELECT ' . $code . ', source_definition.schema_version, source_definition.options, source_definition.pricing_axes, source_definition.active '
            . 'FROM yoowii_supplier_product_mapping_version mapping '
            . 'INNER JOIN yoowii_supplier_product supplier_product ON supplier_product.id = mapping.supplier_product_id '
            . 'INNER JOIN yoowii_print_supplier supplier ON supplier.id = supplier_product.supplier_id '
            . 'INNER JOIN yoowii_print_product_definition source_definition ON source_definition.id = mapping.print_product_definition_id '
            . 'LEFT JOIN yoowii_print_product_definition canonical_definition ON canonical_definition.product_code = ' . $code . ' '
            . "WHERE supplier.code = 'realisaprint' AND canonical_definition.id IS NULL "
            . 'AND NOT EXISTS (SELECT 1 FROM yoowii_supplier_product_mapping_version newer WHERE newer.supplier_product_id = mapping.supplier_product_id AND newer.id > mapping.id)');

        // A definition used by an already-published product must stay usable
        // after its mappings are consolidated.
        $this->addSql('UPDATE yoowii_print_product_definition canonical_definition '
            . 'INNER JOIN yoowii_supplier_product_mapping_version mapping ON 1 = 1 '
            . 'INNER JOIN yoowii_supplier_product supplier_product ON supplier_product.id = mapping.supplier_product_id '
            . 'INNER JOIN yoowii_print_supplier supplier ON supplier.id = supplier_product.supplier_id '
            . 'INNER JOIN yoowii_print_product_definition source_definition ON source_definition.id = mapping.print_product_definition_id '
            . "SET canonical_definition.active = 1 WHERE canonical_definition.product_code = $code AND supplier.code = 'realisaprint' AND source_definition.active = 1");

        $this->addSql('UPDATE yoowii_supplier_product_mapping_version mapping '
            . 'INNER JOIN yoowii_supplier_product supplier_product ON supplier_product.id = mapping.supplier_product_id '
            . 'INNER JOIN yoowii_print_supplier supplier ON supplier.id = supplier_product.supplier_id '
            . 'INNER JOIN yoowii_print_product_definition canonical_definition ON canonical_definition.product_code = ' . $code
            . " SET mapping.print_product_definition_id = canonical_definition.id WHERE supplier.code = 'realisaprint'");

        $this->addSql('UPDATE sylius_product product '
            . 'INNER JOIN yoowii_supplier_product_mapping_version mapping ON mapping.yoowii_product_code = product.code '
            . 'INNER JOIN yoowii_supplier_product supplier_product ON supplier_product.id = mapping.supplier_product_id '
            . 'INNER JOIN yoowii_print_supplier supplier ON supplier.id = supplier_product.supplier_id '
            . 'INNER JOIN yoowii_print_product_definition canonical_definition ON canonical_definition.product_code = ' . $code
            . " SET product.print_definition_code = canonical_definition.product_code WHERE supplier.code = 'realisaprint'");
    }

    public function down(Schema $schema): void
    {
        // This data normalization is intentionally irreversible.
    }
}
