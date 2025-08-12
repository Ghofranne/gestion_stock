<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250809105856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Idempotent: only add columns if they don't exist
        $table = $schema->getTable('user');
        $sqlParts = [];
        if (!$table->hasColumn('prenom')) {
            $sqlParts[] = 'ADD prenom VARCHAR(100) DEFAULT NULL';
        }
        if (!$table->hasColumn('nom')) {
            $sqlParts[] = 'ADD nom VARCHAR(100) DEFAULT NULL';
        }
        if (!$table->hasColumn('telephone')) {
            $sqlParts[] = 'ADD telephone VARCHAR(30) DEFAULT NULL';
        }
        if (!$table->hasColumn('poste')) {
            $sqlParts[] = 'ADD poste VARCHAR(100) DEFAULT NULL';
        }
        if (!$table->hasColumn('last_login_at')) {
            $sqlParts[] = "ADD last_login_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'";
        }
        if (!empty($sqlParts)) {
            $this->addSql('ALTER TABLE user ' . implode(', ', $sqlParts));
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('user');
        $sqlParts = [];
        if ($table->hasColumn('prenom')) { $sqlParts[] = 'DROP prenom'; }
        if ($table->hasColumn('nom')) { $sqlParts[] = 'DROP nom'; }
        if ($table->hasColumn('telephone')) { $sqlParts[] = 'DROP telephone'; }
        if ($table->hasColumn('poste')) { $sqlParts[] = 'DROP poste'; }
        if ($table->hasColumn('last_login_at')) { $sqlParts[] = 'DROP last_login_at'; }
        if (!empty($sqlParts)) {
            $this->addSql('ALTER TABLE user ' . implode(', ', $sqlParts));
        }
    }
}
