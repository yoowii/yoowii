<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the immutable pricing snapshot to Sylius order items.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_item ADD pricing_snapshot JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_order_item DROP pricing_snapshot');
    }
}
