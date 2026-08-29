<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link Sylius print products to a Yoowii print calculator definition.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_product ADD print_definition_code VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_product_print_definition ON sylius_product (print_definition_code)');
        $this->addSql("UPDATE sylius_product SET print_definition_code = code WHERE fulfillment_type = 'print' AND code IN ('PRINT_FLYER', 'PRINT_BUSINESS_CARD')");
        $this->addSql("UPDATE sylius_product p INNER JOIN sylius_product_variant v ON v.product_id = p.id SET p.print_definition_code = v.code WHERE p.fulfillment_type = 'print' AND p.print_definition_code IS NULL AND v.code IN ('PRINT_FLYER', 'PRINT_BUSINESS_CARD')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_product_print_definition ON sylius_product');
        $this->addSql('ALTER TABLE sylius_product DROP print_definition_code');
    }
}
