<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align supplier sourcing index names with the current Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product RENAME INDEX idx_72be41a72add6d8c TO IDX_E8C08E5D2ADD6D8C');
        $this->addSql('ALTER TABLE yoowii_supplier_pricing_matrix_version RENAME INDEX idx_cf4e19bc8241e9b7 TO IDX_E46ADDFC2475ABB3');
        $this->addSql('ALTER TABLE yoowii_supplier_route RENAME INDEX idx_54348edc8241e9b7 TO IDX_A6330BF52475ABB3');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE yoowii_supplier_product RENAME INDEX IDX_E8C08E5D2ADD6D8C TO idx_72be41a72add6d8c');
        $this->addSql('ALTER TABLE yoowii_supplier_pricing_matrix_version RENAME INDEX IDX_E46ADDFC2475ABB3 TO idx_cf4e19bc8241e9b7');
        $this->addSql('ALTER TABLE yoowii_supplier_route RENAME INDEX IDX_A6330BF52475ABB3 TO idx_54348edc8241e9b7');
    }
}
