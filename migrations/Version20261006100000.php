<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261006100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the pre-publication Realisaprint configurator state on its existing validation snapshot.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation ADD initial_configurator_state JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_realisaprint_mapping_validation DROP initial_configurator_state');
    }
}
