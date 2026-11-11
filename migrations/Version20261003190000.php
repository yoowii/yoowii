<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store immutable Realisaprint mapping coverage and sample quote validations.'; }
    public function up(Schema $schema): void { $this->addSql('CREATE TABLE yoowii_realisaprint_mapping_validation (id INT AUTO_INCREMENT NOT NULL, mapping_id INT NOT NULL, coverage_complete TINYINT(1) NOT NULL, quote_passed TINYINT(1) NOT NULL, supplier_cost INT DEFAULT NULL, coverage_errors JSON NOT NULL, technical_detail VARCHAR(280) DEFAULT NULL, checked_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_realisaprint_mapping_validation (mapping_id, checked_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'); $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation ADD CONSTRAINT FK_REALISAPRINT_MAPPING_VALIDATION FOREIGN KEY (mapping_id) REFERENCES yoowii_supplier_product_mapping_version (id) ON DELETE CASCADE'); }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE yoowii_realisaprint_mapping_validation'); }
}
