<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20260904100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store idempotent supplier artwork FTP transfers.'; }
    public function up(Schema $schema): void { $this->addSql("CREATE TABLE yoowii_print_job_supplier_file_transfer (id INT AUTO_INCREMENT NOT NULL, submission_id INT NOT NULL, print_asset_id INT NOT NULL, remote_path VARCHAR(1024) NOT NULL, status VARCHAR(32) NOT NULL, attempt_count INT DEFAULT 0 NOT NULL, last_error LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_print_supplier_file_transfer (submission_id, print_asset_id), INDEX IDX_PRINT_SUPPLIER_FILE_SUBMISSION (submission_id), INDEX IDX_PRINT_SUPPLIER_FILE_ASSET (print_asset_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB"); $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer ADD CONSTRAINT FK_PRINT_SUPPLIER_FILE_SUBMISSION FOREIGN KEY (submission_id) REFERENCES yoowii_print_job_supplier_submission (id) ON DELETE CASCADE'); $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer ADD CONSTRAINT FK_PRINT_SUPPLIER_FILE_ASSET FOREIGN KEY (print_asset_id) REFERENCES yoowii_print_asset (id) ON DELETE RESTRICT'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer DROP FOREIGN KEY FK_PRINT_SUPPLIER_FILE_SUBMISSION'); $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer DROP FOREIGN KEY FK_PRINT_SUPPLIER_FILE_ASSET'); $this->addSql('DROP TABLE yoowii_print_job_supplier_file_transfer'); }
}
