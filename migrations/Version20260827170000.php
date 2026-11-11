<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Yoowii fulfilment type to Sylius products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sylius_product ADD fulfillment_type VARCHAR(32) DEFAULT 'quote_only' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product DROP fulfillment_type');
    }
}
