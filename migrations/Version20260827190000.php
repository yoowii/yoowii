<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add multi-supplier print sourcing, versioned mappings, pricing matrices and fixed routes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yoowii_print_supplier (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, integration_mode VARCHAR(16) NOT NULL, capabilities JSON NOT NULL, active TINYINT(1) NOT NULL, UNIQUE INDEX uniq_print_supplier_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yoowii_supplier_product (id INT AUTO_INCREMENT NOT NULL, supplier_id INT NOT NULL, code VARCHAR(128) NOT NULL, name VARCHAR(255) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_72BE41A72ADD6D8C (supplier_id), UNIQUE INDEX uniq_supplier_product_code (supplier_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yoowii_supplier_product_mapping_version (id INT AUTO_INCREMENT NOT NULL, supplier_product_id INT NOT NULL, yoowii_product_code VARCHAR(64) NOT NULL, version VARCHAR(64) NOT NULL, configuration_mapping JSON NOT NULL, effective_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', effective_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', active TINYINT(1) NOT NULL, INDEX IDX_D23518858241E9B7 (supplier_product_id), UNIQUE INDEX uniq_supplier_product_mapping_version (supplier_product_id, yoowii_product_code, version), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yoowii_supplier_pricing_matrix_version (id INT AUTO_INCREMENT NOT NULL, supplier_product_id INT NOT NULL, version VARCHAR(64) NOT NULL, currency_code VARCHAR(3) NOT NULL, matrix JSON NOT NULL, effective_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', imported_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(16) NOT NULL, checksum VARCHAR(64) NOT NULL, activated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CF4E19BC8241E9B7 (supplier_product_id), UNIQUE INDEX uniq_supplier_pricing_matrix_version (supplier_product_id, version), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE yoowii_supplier_route (id INT AUTO_INCREMENT NOT NULL, supplier_product_id INT NOT NULL, yoowii_product_code VARCHAR(64) NOT NULL, priority INT NOT NULL, effective_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', effective_until DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', active TINYINT(1) NOT NULL, INDEX IDX_54348EDC8241E9B7 (supplier_product_id), INDEX idx_supplier_route_lookup (yoowii_product_code, active, priority), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE yoowii_supplier_product ADD CONSTRAINT FK_72BE41A72ADD6D8C FOREIGN KEY (supplier_id) REFERENCES yoowii_print_supplier (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version ADD CONSTRAINT FK_D23518858241E9B7 FOREIGN KEY (supplier_product_id) REFERENCES yoowii_supplier_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE yoowii_supplier_pricing_matrix_version ADD CONSTRAINT FK_CF4E19BC8241E9B7 FOREIGN KEY (supplier_product_id) REFERENCES yoowii_supplier_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE yoowii_supplier_route ADD CONSTRAINT FK_54348EDC8241E9B7 FOREIGN KEY (supplier_product_id) REFERENCES yoowii_supplier_product (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version DROP FOREIGN KEY FK_D23518858241E9B7');
        $this->addSql('ALTER TABLE yoowii_supplier_pricing_matrix_version DROP FOREIGN KEY FK_CF4E19BC8241E9B7');
        $this->addSql('ALTER TABLE yoowii_supplier_route DROP FOREIGN KEY FK_54348EDC8241E9B7');
        $this->addSql('ALTER TABLE yoowii_supplier_product DROP FOREIGN KEY FK_72BE41A72ADD6D8C');
        $this->addSql('DROP TABLE yoowii_supplier_product_mapping_version');
        $this->addSql('DROP TABLE yoowii_supplier_pricing_matrix_version');
        $this->addSql('DROP TABLE yoowii_supplier_route');
        $this->addSql('DROP TABLE yoowii_supplier_product');
        $this->addSql('DROP TABLE yoowii_print_supplier');
    }
}
