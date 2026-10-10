<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261010130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store Prescript availability by Realisaprint supplier stock.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE yoowii_realisaprint_catalog_product ADD prescript_stocks JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("UPDATE yoowii_realisaprint_catalog_product SET prescript_stocks = JSON_OBJECT() WHERE prescript_stocks IS NULL");
        $this->addSql("ALTER TABLE yoowii_realisaprint_catalog_product MODIFY prescript_stocks JSON NOT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_catalog_product DROP prescript_stocks');
    }
}
