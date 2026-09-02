<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the print job notification outbox table with its Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_notification DROP subject, DROP content');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_notification ADD subject VARCHAR(255) NOT NULL, ADD content LONGTEXT NOT NULL');
    }
}
