<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initialize historical Realisaprint mapping-validation test snapshots.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE yoowii_realisaprint_mapping_validation SET test_configuration = JSON_OBJECT(), test_fingerprint = REPEAT('0', 64) WHERE test_configuration IS NULL OR test_fingerprint IS NULL OR test_fingerprint = ''");
    }

    public function down(Schema $schema): void
    {
    }
}
