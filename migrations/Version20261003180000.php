<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted definitions for generic Yoowii print configurators.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE yoowii_print_product_definition (id INT AUTO_INCREMENT NOT NULL, product_code VARCHAR(64) NOT NULL, schema_version VARCHAR(32) NOT NULL, options JSON NOT NULL, pricing_axes JSON NOT NULL, active TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_YOOWII_PRINT_DEFINITION_CODE (product_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yoowii_print_product_definition');
    }
}
