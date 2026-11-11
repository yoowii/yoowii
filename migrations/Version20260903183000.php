<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903183000 extends AbstractMigration
{
    public function getDescription(): string { return 'Store idempotent Realisaprint supplier order submissions.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_print_job_supplier_submission (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, status VARCHAR(32) NOT NULL, attempt_count INT DEFAULT 0 NOT NULL, supplier_order_id VARCHAR(128) DEFAULT NULL, request_payload JSON DEFAULT NULL, response_payload JSON DEFAULT NULL, last_error LONGTEXT DEFAULT NULL, idempotency_key VARCHAR(128) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_print_supplier_submission_job (print_job_id), UNIQUE INDEX UNIQ_PRINT_SUPPLIER_SUBMISSION_KEY (idempotency_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission ADD CONSTRAINT FK_PRINT_SUPPLIER_SUBMISSION_JOB FOREIGN KEY (print_job_id) REFERENCES yoowii_print_job (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission DROP FOREIGN KEY FK_PRINT_SUPPLIER_SUBMISSION_JOB');
        $this->addSql('DROP TABLE yoowii_print_job_supplier_submission');
    }
}
