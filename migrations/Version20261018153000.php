<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unreferenced legacy print definitions superseded by canonical Realisaprint definitions.';
    }

    public function up(Schema $schema): void
    {
        $codes = "'PRINT_AGENDA', 'PRINT_AGENDA_SEMAINIER', 'PRINT_CARTE_DE_VISITE', 'PRINT_AGENDA_DE_POCHE', 'PRINT_DRAPEAU', 'PRINT_BADGE_BOUTON', 'PRINT_BADGE_MIROIR', 'PRINT_BARNUM', 'PRINT_ADH_SIF_LETTRAGE', 'PRINT_MUG_BOUTEILLE', 'PRINT_MUG_BOUTEILLE_GOURMAND', 'PRINT_AFFICHE', 'PRINT_BACHE', 'PRINT_BACHE_HABILLAGE_BARRIERE_RECTO', 'PRINT_CARTE_DE_VISITE_CALENDRIER', 'PRINT_AGENDA_JOURNALIER', 'PRINT_AGENDA_MENSUEL', 'PRINT_AGENDA_PROFESSIONNEL', 'PRINT_AGENDA_SPIRALE', 'PRINT_DOMING_BOSSING', 'PRINT_MAGNET_BOUTIQUE'";
        $this->addSql('DELETE definition FROM yoowii_print_product_definition definition '
            . 'LEFT JOIN yoowii_supplier_product_mapping_version mapping ON mapping.print_product_definition_id = definition.id '
            . 'LEFT JOIN sylius_product product ON product.print_definition_code = definition.product_code '
            . 'LEFT JOIN yoowii_supplier_pricing_matrix_version matrix_version ON JSON_UNQUOTE(JSON_EXTRACT(matrix_version.matrix, \'$.product_code\')) = definition.product_code '
            . 'LEFT JOIN sylius_order_item order_item ON JSON_SEARCH(order_item.pricing_snapshot, \'one\', definition.product_code) IS NOT NULL '
            . 'LEFT JOIN yoowii_print_job print_job ON JSON_SEARCH(print_job.production_snapshot, \'one\', definition.product_code) IS NOT NULL '
            . 'WHERE definition.product_code IN (' . $codes . ') '
            . 'AND mapping.id IS NULL AND product.id IS NULL AND matrix_version.id IS NULL AND order_item.id IS NULL AND print_job.id IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Removed legacy definitions are intentionally not reconstructed.
    }
}
