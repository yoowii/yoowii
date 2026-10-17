<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store persistent RealiSAPrint catalogue configuration synchronization reports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yoowii_realisaprint_configuration_sync_run (id INT AUTO_INCREMENT NOT NULL, mode VARCHAR(12) NOT NULL, status VARCHAR(32) NOT NULL, active_lock VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', finished_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', report JSON NOT NULL, UNIQUE INDEX uniq_realisaprint_configuration_sync_active (active_lock), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yoowii_realisaprint_configuration_sync_run');
    }
}
