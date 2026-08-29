<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the unmapped print definition index from Sylius products.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_product_print_definition ON sylius_product');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_product_print_definition ON sylius_product (print_definition_code)');
    }
}
