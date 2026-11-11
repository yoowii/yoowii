<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize the print job note index name with Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_note RENAME INDEX IDX_224E5E80727301B TO IDX_54D33935727301B');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job_note RENAME INDEX IDX_54D33935727301B TO IDX_224E5E80727301B');
    }
}
