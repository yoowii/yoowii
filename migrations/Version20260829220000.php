<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align print asset index names with the Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_asset RENAME INDEX uniq_print_asset_storage TO UNIQ_34AE1CE1111795A5');
        $this->addSql('ALTER TABLE yoowii_print_asset RENAME INDEX idx_print_asset_job TO IDX_34AE1CE1727301B');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_asset RENAME INDEX UNIQ_34AE1CE1111795A5 TO uniq_print_asset_storage');
        $this->addSql('ALTER TABLE yoowii_print_asset RENAME INDEX IDX_34AE1CE1727301B TO idx_print_asset_job');
    }
}
