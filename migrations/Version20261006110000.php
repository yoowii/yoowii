<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep complete redacted Realisaprint validation diagnostics.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation MODIFY technical_detail LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation MODIFY technical_detail VARCHAR(280) DEFAULT NULL');
    }
}
