<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921114830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Liens de réinitialisation du mot de passe';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE password_reset_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, token_hash VARCHAR(64) NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_6B7BA4B6A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6B7BA4B6B3BC57DA ON password_reset_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_6B7BA4B6F9D83E2 ON password_reset_token (expires_at)');
        $this->addSql('CREATE INDEX IDX_6B7BA4B6A76ED395 ON password_reset_token (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE password_reset_token');
    }
}
