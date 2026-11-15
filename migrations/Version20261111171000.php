<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist idempotent Prescript cart operations independently from browser sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE yoowii_prescript_cart_operation (id INT AUTO_INCREMENT NOT NULL, cart_id INT NOT NULL, product_id INT NOT NULL, variant_id INT NOT NULL, order_item_id INT DEFAULT NULL, idempotency_key_hash VARCHAR(64) NOT NULL, payload_fingerprint VARCHAR(64) NOT NULL, mapping_version VARCHAR(128) NOT NULL, configuration_code VARCHAR(128) DEFAULT NULL, status VARCHAR(32) NOT NULL, attempt_id VARCHAR(64) DEFAULT NULL, attempt_started_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_prescript_cart_operation_key (idempotency_key_hash), INDEX idx_prescript_cart_operation_cart_status (cart_id, status), INDEX idx_prescript_cart_operation_lease (status, attempt_started_at), INDEX IDX_PRESCRIPT_CART_PRODUCT (product_id), INDEX IDX_PRESCRIPT_CART_VARIANT (variant_id), INDEX IDX_PRESCRIPT_CART_ITEM (order_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE yoowii_prescript_cart_operation ADD CONSTRAINT FK_PRESCRIPT_CART_OPERATION_CART FOREIGN KEY (cart_id) REFERENCES sylius_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE yoowii_prescript_cart_operation ADD CONSTRAINT FK_PRESCRIPT_CART_OPERATION_PRODUCT FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE yoowii_prescript_cart_operation ADD CONSTRAINT FK_PRESCRIPT_CART_OPERATION_VARIANT FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE yoowii_prescript_cart_operation ADD CONSTRAINT FK_PRESCRIPT_CART_OPERATION_ITEM FOREIGN KEY (order_item_id) REFERENCES sylius_order_item (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE yoowii_prescript_cart_operation');
    }
}
