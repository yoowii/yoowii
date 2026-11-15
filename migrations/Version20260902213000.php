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
        // The original notification-table migration did not create these
        // legacy columns. Production databases that had them still need them
        // removed, while a fresh install must remain migratable.
        if (!$schema->hasTable('yoowii_print_job_notification')) {
            return;
        }
        $table = $schema->getTable('yoowii_print_job_notification');
        if ($table->hasColumn('subject')) {
            $this->addSql('ALTER TABLE yoowii_print_job_notification DROP subject');
        }
        if ($table->hasColumn('content')) {
            $this->addSql('ALTER TABLE yoowii_print_job_notification DROP content');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_notification ADD subject VARCHAR(255) NOT NULL, ADD content LONGTEXT NOT NULL');
    }
}
