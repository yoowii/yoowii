<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Version print definitions per supplier mapping to support inactive revisions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_product_definition DROP INDEX UNIQ_YOOWII_PRINT_DEFINITION_CODE');
        $this->addSql('CREATE INDEX IDX_PRINT_DEFINITION_PRODUCT_ACTIVE ON yoowii_print_product_definition (product_code, active)');
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version ADD print_product_definition_id INT DEFAULT NULL');
        $this->addSql('UPDATE yoowii_supplier_product_mapping_version mapping INNER JOIN yoowii_print_product_definition definition ON definition.product_code = mapping.yoowii_product_code SET mapping.print_product_definition_id = definition.id');
        $this->addSql('CREATE INDEX IDX_MAPPING_PRINT_DEFINITION ON yoowii_supplier_product_mapping_version (print_product_definition_id)');
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version ADD CONSTRAINT FK_MAPPING_PRINT_DEFINITION FOREIGN KEY (print_product_definition_id) REFERENCES yoowii_print_product_definition (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version DROP FOREIGN KEY FK_MAPPING_PRINT_DEFINITION');
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version DROP INDEX IDX_MAPPING_PRINT_DEFINITION, DROP print_product_definition_id');
        $this->addSql('DROP INDEX IDX_PRINT_DEFINITION_PRODUCT_ACTIVE ON yoowii_print_product_definition');
        $this->addSql('ALTER TABLE yoowii_print_product_definition ADD UNIQUE INDEX UNIQ_YOOWII_PRINT_DEFINITION_CODE (product_code)');
    }
}
