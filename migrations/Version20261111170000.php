<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add durable Realisaprint submission leases and quarantine historical ambiguous failures.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission ADD attempt_id VARCHAR(64) DEFAULT NULL, ADD attempt_started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_print_supplier_submission_status ON yoowii_print_job_supplier_submission (status, attempt_started_at)');
        // Existing failures cannot prove that create_order was not accepted remotely.
        $this->addSql("UPDATE yoowii_print_job_supplier_submission SET status = 'pending_confirmation', last_error = COALESCE(last_error, 'Historical submission outcome requires manual confirmation.') WHERE status = 'failed'");
    }

    public function down(Schema $schema): void
    {
        throw new \RuntimeException('Irreversible: historical failed submissions were quarantined as pending_confirmation to prevent unsafe retries. Restore from a pre-migration backup only after manual supplier reconciliation.');
    }
}
