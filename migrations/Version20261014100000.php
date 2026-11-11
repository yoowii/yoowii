<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track publication and archival state for supplier mapping version retention.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE yoowii_supplier_product_mapping_version ADD published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD archived_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        // Historic rows predate publication tracking: retain them conservatively.
        $this->addSql('UPDATE yoowii_supplier_product_mapping_version SET published_at = effective_from');
        $this->addSql('CREATE INDEX IDX_MAPPING_RETENTION ON yoowii_supplier_product_mapping_version (active, published_at, effective_from)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product_mapping_version DROP INDEX IDX_MAPPING_RETENTION, DROP published_at, DROP archived_at');
    }
}
