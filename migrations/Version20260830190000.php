<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add operational reasons, due dates and internal notes to print production jobs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE yoowii_print_job ADD status_reason LONGTEXT DEFAULT NULL, ADD due_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql("CREATE TABLE yoowii_print_job_note (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, message LONGTEXT NOT NULL, author VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_224E5E80727301B (print_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_job_note ADD CONSTRAINT FK_PRINT_NOTE_JOB FOREIGN KEY (print_job_id) REFERENCES yoowii_print_job (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_note DROP FOREIGN KEY FK_PRINT_NOTE_JOB');
        $this->addSql('DROP TABLE yoowii_print_job_note');
        $this->addSql('ALTER TABLE yoowii_print_job DROP status_reason, DROP due_at');
    }
}
