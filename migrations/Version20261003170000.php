<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store the read-only synchronized Realisaprint catalogue and product configurations.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yoowii_realisaprint_catalog_product (id INT AUTO_INCREMENT NOT NULL, provider_product_id VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, configuration JSON DEFAULT NULL, archived TINYINT(1) NOT NULL, last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', configuration_synced_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_realisaprint_catalog_product (provider_product_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE yoowii_realisaprint_catalog_product'); }
}
