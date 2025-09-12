<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812234027 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Idempotent checks to avoid failures on existing schemas
        $schemaManager = $this->connection->createSchemaManager();

        // Create user_activity if it doesn't exist
        if (!$schemaManager->tablesExist(['user_activity'])) {
            $this->addSql('CREATE TABLE user_activity (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT DEFAULT NULL, entity_name VARCHAR(255) DEFAULT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, INDEX IDX_4CF9ED5AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            $this->addSql('ALTER TABLE user_activity ADD CONSTRAINT FK_4CF9ED5AA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        }

        // Ensure mouvement_stock foreign key and index exist
        if ($schemaManager->tablesExist(['mouvement_stock'])) {
            $table = $schemaManager->introspectTable('mouvement_stock');

            // Add index on created_by_id if missing
            $hasIndex = false;
            foreach ($table->getIndexes() as $index) {
                if (in_array('created_by_id', $index->getColumns(), true)) {
                    $hasIndex = true;
                    break;
                }
            }
            if (!$hasIndex) {
                $this->addSql('CREATE INDEX IDX_61E2C8EBB03A8386 ON mouvement_stock (created_by_id)');
            }

            // Data hygiene: set created_by_id to NULL where it references non-existing user rows
            $this->addSql('UPDATE mouvement_stock ms LEFT JOIN `user` u ON ms.created_by_id = u.id SET ms.created_by_id = NULL WHERE ms.created_by_id IS NOT NULL AND u.id IS NULL');

            // Ensure column is nullable before adding FK (to allow NULLs)
            $createdByCol = $table->getColumn('created_by_id');
            if (!$createdByCol->getNotnull()) {
                // already nullable
            } else {
                $this->addSql('ALTER TABLE mouvement_stock MODIFY created_by_id INT DEFAULT NULL');
                // refresh table metadata
                $table = $schemaManager->introspectTable('mouvement_stock');
            }

            // Add FK to user(id) on created_by_id if missing
            $hasFk = false;
            foreach ($table->getForeignKeys() as $fk) {
                if (in_array('created_by_id', $fk->getLocalColumns(), true)) {
                    $hasFk = true;
                    break;
                }
            }
            if (!$hasFk) {
                $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EBB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_activity DROP FOREIGN KEY FK_4CF9ED5AA76ED395');
        $this->addSql('DROP TABLE user_activity');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EBB03A8386');
        $this->addSql('DROP INDEX IDX_61E2C8EBB03A8386 ON mouvement_stock');
    }
}
