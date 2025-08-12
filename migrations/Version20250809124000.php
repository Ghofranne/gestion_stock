<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250809124000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add resetToken and resetRequestedAt to user (idempotent)';
    }

    public function up(Schema $schema): void
    {
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
