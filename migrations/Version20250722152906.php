<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250722152906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE depot (id INT AUTO_INCREMENT NOT NULL, societe_id INT DEFAULT NULL, nom VARCHAR(255) NOT NULL, adresse VARCHAR(255) NOT NULL, INDEX IDX_47948BBCFCF77503 (societe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipement (id INT AUTO_INCREMENT NOT NULL, type_equipement VARCHAR(255) NOT NULL, sn VARCHAR(255) DEFAULT NULL, model VARCHAR(255) DEFAULT NULL, imei VARCHAR(255) DEFAULT NULL, num_appel VARCHAR(255) DEFAULT NULL, operateur VARCHAR(255) DEFAULT NULL, reference_carte VARCHAR(255) DEFAULT NULL, fournisseur VARCHAR(255) NOT NULL, etat VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE mouvement_stock (id INT AUTO_INCREMENT NOT NULL, equipement_id INT DEFAULT NULL, depot_source_id INT DEFAULT NULL, depot_destination_id INT DEFAULT NULL, type_mouvement VARCHAR(255) NOT NULL, date DATETIME NOT NULL, INDEX IDX_61E2C8EB806F0F5C (equipement_id), INDEX IDX_61E2C8EB820CB9A5 (depot_source_id), INDEX IDX_61E2C8EBC6A4A76A (depot_destination_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE depot ADD CONSTRAINT FK_47948BBCFCF77503 FOREIGN KEY (societe_id) REFERENCES societe (id)');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EB806F0F5C FOREIGN KEY (equipement_id) REFERENCES equipement (id)');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EB820CB9A5 FOREIGN KEY (depot_source_id) REFERENCES depot (id)');
        $this->addSql('ALTER TABLE mouvement_stock ADD CONSTRAINT FK_61E2C8EBC6A4A76A FOREIGN KEY (depot_destination_id) REFERENCES depot (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE depot DROP FOREIGN KEY FK_47948BBCFCF77503');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EB806F0F5C');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EB820CB9A5');
        $this->addSql('ALTER TABLE mouvement_stock DROP FOREIGN KEY FK_61E2C8EBC6A4A76A');
        $this->addSql('DROP TABLE depot');
        $this->addSql('DROP TABLE equipement');
        $this->addSql('DROP TABLE mouvement_stock');
    }
}
