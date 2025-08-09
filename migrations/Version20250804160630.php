<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250804160630 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Rendre idempotent: n'ajoute la colonne que si elle n'existe pas déjà
        $table = $schema->getTable('mouvement_stock');
        if (!$table->hasColumn('quantite')) {
            $this->addSql('ALTER TABLE mouvement_stock ADD quantite INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('mouvement_stock');
        if ($table->hasColumn('quantite')) {
            $this->addSql('ALTER TABLE mouvement_stock DROP quantite');
        }
    }
}
