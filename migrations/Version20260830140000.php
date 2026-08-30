<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add revocable guest links and auditable artwork replacement to print production.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_job ADD access_version INT DEFAULT 1 NOT NULL, ADD guest_access_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql("ALTER TABLE yoowii_print_asset ADD superseded_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_print_asset DROP superseded_at');
        $this->addSql('ALTER TABLE yoowii_print_job DROP access_version, DROP guest_access_enabled');
    }
}
