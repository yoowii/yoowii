<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store customer-visible print job messages such as BAT rejection reasons.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_print_job_customer_message (id INT AUTO_INCREMENT NOT NULL, print_job_id INT NOT NULL, type VARCHAR(64) NOT NULL, message LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_3730A79D727301B (print_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_print_job_customer_message ADD CONSTRAINT FK_PRINT_CUSTOMER_MESSAGE_JOB FOREIGN KEY (print_job_id) REFERENCES yoowii_print_job (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_customer_message DROP FOREIGN KEY FK_PRINT_CUSTOMER_MESSAGE_JOB');
        $this->addSql('DROP TABLE yoowii_print_job_customer_message');
    }
}
