<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store immutable technical preflight reports for each customer artwork asset.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_print_preflight_report (id INT AUTO_INCREMENT NOT NULL, print_asset_id INT NOT NULL, status VARCHAR(16) NOT NULL, report JSON NOT NULL, analysed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', asset_checksum VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_print_preflight_asset (print_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_preflight_report ADD CONSTRAINT FK_PRINT_PREFLIGHT_ASSET FOREIGN KEY (print_asset_id) REFERENCES yoowii_print_asset (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_preflight_report DROP FOREIGN KEY FK_PRINT_PREFLIGHT_ASSET');
        $this->addSql('DROP TABLE yoowii_print_preflight_report');
    }
}
