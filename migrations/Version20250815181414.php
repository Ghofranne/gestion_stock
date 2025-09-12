<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250815181414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Make idempotent: check schema before altering
        $sm = $this->connection->createSchemaManager();
        $schemaIntrospected = $sm->introspectSchema();

        // Add description to equipement if missing
        if ($schemaIntrospected->hasTable('equipement')) {
            $equipement = $schemaIntrospected->getTable('equipement');
            if (!$equipement->hasColumn('description')) {
                $this->addSql('ALTER TABLE equipement ADD description LONGTEXT DEFAULT NULL');
            }
        }

        // Add FK and index on mouvement_stock.created_by_id if missing
        if ($schemaIntrospected->hasTable('mouvement_stock')) {
            $mvt = $schemaIntrospected->getTable('mouvement_stock');

            // Index check
            if (!$mvt->hasIndex('IDX_61E2C8EBB03A8386')) {
                $this->addSql('CREATE INDEX IDX_61E2C8EBB03A8386 ON mouvement_stock (created_by_id)');
            }

            // FK check
            $hasFk = false;
            foreach ($mvt->getForeignKeys() as $fk) {
                if ($fk->getName() === 'FK_61E2C8EBB03A8386') {
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
        $this->addSql('ALTER TABLE equipement DROP description');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EBB03A8386');
        $this->addSql('DROP INDEX IDX_61E2C8EBB03A8386 ON mouvement_stock');
    }
}
