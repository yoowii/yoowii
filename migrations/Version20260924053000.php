<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924053000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align supplier submission and transfer index names with Doctrine metadata.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer RENAME INDEX idx_print_supplier_file_submission TO IDX_282FE5F5E1FD4933');
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer RENAME INDEX idx_print_supplier_file_asset TO IDX_282FE5F564948587');
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission RENAME INDEX uniq_print_supplier_submission_job TO UNIQ_DC39BF58727301B');
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission RENAME INDEX uniq_print_supplier_submission_key TO UNIQ_DC39BF587FD1C147');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer RENAME INDEX IDX_282FE5F5E1FD4933 TO idx_print_supplier_file_submission');
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_file_transfer RENAME INDEX IDX_282FE5F564948587 TO idx_print_supplier_file_asset');
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission RENAME INDEX UNIQ_DC39BF58727301B TO uniq_print_supplier_submission_job');
        $this->addSql('ALTER TABLE yoowii_print_job_supplier_submission RENAME INDEX UNIQ_DC39BF587FD1C147 TO uniq_print_supplier_submission_key');
    }
}
