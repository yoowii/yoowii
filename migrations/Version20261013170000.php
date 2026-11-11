<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow empty free height and width dimensions in existing print definitions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE yoowii_print_product_definition SET options = JSON_SET(options, '$.hauteur_cm.required', false, '$.largeur_cm.required', false) WHERE JSON_CONTAINS_PATH(options, 'all', '$.hauteur_cm', '$.largeur_cm')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE yoowii_print_product_definition SET options = JSON_SET(options, '$.hauteur_cm.required', true, '$.largeur_cm.required', true) WHERE JSON_CONTAINS_PATH(options, 'all', '$.hauteur_cm', '$.largeur_cm')");
    }
}
