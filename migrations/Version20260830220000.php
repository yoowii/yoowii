<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an idempotent email notification outbox for print jobs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_print_job_notification (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, fingerprint VARCHAR(128) NOT NULL, type VARCHAR(64) NOT NULL, recipient VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_print_notification_fingerprint (fingerprint), INDEX IDX_9C3D9B1D727301B (print_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_job_notification ADD CONSTRAINT FK_PRINT_NOTIFICATION_JOB FOREIGN KEY (print_job_id) REFERENCES yoowii_print_job (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_notification DROP FOREIGN KEY FK_PRINT_NOTIFICATION_JOB');
        $this->addSql('DROP TABLE yoowii_print_job_notification');
    }
}
