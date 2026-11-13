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
        // Version20261004173000 already introduced these columns. Keep this
        // migration compatible with databases that predate that deployment.
        if (!$schema->hasTable('yoowii_realisaprint_mapping_validation')) {
            return;
        }
        $table = $schema->getTable('yoowii_realisaprint_mapping_validation');
        if (!$table->hasColumn('test_configuration')) {
            $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation ADD test_configuration JSON NOT NULL');
        }
        if (!$table->hasColumn('test_fingerprint')) {
            $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation ADD test_fingerprint VARCHAR(64) NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation DROP test_configuration, DROP test_fingerprint');
    }
}
