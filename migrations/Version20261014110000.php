<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Yoowii storefront main navigation configuration to taxons.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_taxon ADD show_in_main_navigation TINYINT(1) DEFAULT 0 NOT NULL, ADD main_navigation_position INT DEFAULT NULL, ADD main_navigation_label VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_taxon DROP show_in_main_navigation, DROP main_navigation_position, DROP main_navigation_label');
    }
}
