<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Realisaprint price breakdown and test configuration used by a mapping validation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation ADD supplier_base_cost INT DEFAULT NULL, ADD supplier_options_cost INT DEFAULT NULL, ADD test_configuration JSON NOT NULL, ADD test_fingerprint VARCHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation DROP supplier_base_cost, DROP supplier_options_cost, DROP test_configuration, DROP test_fingerprint');
    }
}
