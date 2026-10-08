<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261007110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the Realisaprint initial display state independently from price validations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version ADD initial_display_state JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version DROP initial_display_state');
    }
}
