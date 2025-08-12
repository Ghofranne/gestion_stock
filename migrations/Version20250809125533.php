<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250809125533 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Idempotent: add only if columns don't exist
        $table = $schema->getTable('user');
        $sqlParts = [];
        if (!$table->hasColumn('reset_token')) {
            $sqlParts[] = 'ADD reset_token VARCHAR(64) DEFAULT NULL';
        }
        if (!$table->hasColumn('reset_requested_at')) {
            $sqlParts[] = "ADD reset_requested_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'";
        }
        if (!empty($sqlParts)) {
            $this->addSql('ALTER TABLE user ' . implode(', ', $sqlParts));
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('user');
        $sqlParts = [];
        if ($table->hasColumn('reset_token')) { $sqlParts[] = 'DROP reset_token'; }
        if ($table->hasColumn('reset_requested_at')) { $sqlParts[] = 'DROP reset_requested_at'; }
        if (!empty($sqlParts)) {
            $this->addSql('ALTER TABLE user ' . implode(', ', $sqlParts));
        }
    }
}
