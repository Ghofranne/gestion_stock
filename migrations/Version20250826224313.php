<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250826224313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE password_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F352144A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE password_history ADD CONSTRAINT FK_F352144A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL, ADD two_factor_default_method VARCHAR(20) DEFAULT \'email\' NOT NULL, ADD two_factor_backup_codes JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', ADD two_factor_email_code VARCHAR(10) DEFAULT NULL, ADD two_factor_email_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE enabled enabled TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE password_history DROP FOREIGN KEY FK_F352144A76ED395');
        $this->addSql('DROP TABLE password_history');
        $this->addSql('ALTER TABLE user DROP two_factor_enabled, DROP two_factor_default_method, DROP two_factor_backup_codes, DROP two_factor_email_code, DROP two_factor_email_expires_at, CHANGE enabled enabled TINYINT(1) NOT NULL');
    }
}
