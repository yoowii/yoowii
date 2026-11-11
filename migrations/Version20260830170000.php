<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add an auditable operator activity journal for print production jobs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_print_job_activity (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, action VARCHAR(64) NOT NULL, actor VARCHAR(255) DEFAULT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_BBB0E5FD727301B (print_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_job_activity ADD CONSTRAINT FK_PRINT_ACTIVITY_JOB FOREIGN KEY (print_job_id) REFERENCES yoowii_print_job (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_activity DROP FOREIGN KEY FK_PRINT_ACTIVITY_JOB');
        $this->addSql('DROP TABLE yoowii_print_job_activity');
    }
}
