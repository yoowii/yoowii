<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829213000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create idempotent print jobs and private print asset metadata.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_print_job (id INT AUTO_INCREMENT NOT NULL, order_item_id INT NOT NULL, reference VARCHAR(40) NOT NULL, supplier_code VARCHAR(64) NOT NULL, supplier_product_code VARCHAR(128) NOT NULL, production_snapshot JSON NOT NULL, status VARCHAR(32) NOT NULL, supplier_order_reference VARCHAR(128) DEFAULT NULL, tracking_number VARCHAR(128) DEFAULT NULL, tracking_url VARCHAR(2048) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_print_job_order_item (order_item_id), UNIQUE INDEX uniq_print_job_reference (reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE yoowii_print_asset (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, type VARCHAR(32) NOT NULL, original_name VARCHAR(255) NOT NULL, storage_key VARCHAR(512) NOT NULL, mime_type VARCHAR(127) NOT NULL, size BIGINT NOT NULL, checksum VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_PRINT_ASSET_JOB (print_job_id), UNIQUE INDEX UNIQ_PRINT_ASSET_STORAGE (storage_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_job ADD CONSTRAINT FK_PRINT_JOB_ITEM FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id)');
        $this->addSql('ALTER TABLE yoowii_print_asset ADD CONSTRAINT FK_PRINT_ASSET_JOB FOREIGN KEY (print_job_id) REFERENCES yoowii_print_job (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_asset DROP FOREIGN KEY FK_PRINT_ASSET_JOB');
        $this->addSql('ALTER TABLE yoowii_print_job DROP FOREIGN KEY FK_PRINT_JOB_ITEM');
        $this->addSql('DROP TABLE yoowii_print_asset');
        $this->addSql('DROP TABLE yoowii_print_job');
    }
}
