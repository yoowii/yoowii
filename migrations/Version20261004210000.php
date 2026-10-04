<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the exact Realisaprint mapping-validation test configuration and fingerprint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation ADD test_configuration JSON NOT NULL, ADD test_fingerprint VARCHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation DROP test_configuration, DROP test_fingerprint');
    }
}
