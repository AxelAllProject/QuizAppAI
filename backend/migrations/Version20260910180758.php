<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910180758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clés IA : fonctionnalité premium donnant un nombre fixe de générations de quiz, avec expiration facultative';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ai_key (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value VARCHAR(40) NOT NULL, total_generations INTEGER NOT NULL, remaining_generations INTEGER NOT NULL, label VARCHAR(120) DEFAULT NULL, created_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, redeemed_by_name VARCHAR(60) DEFAULT NULL, redeemed_at DATETIME DEFAULT NULL, redeemed_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_F7052F2FBC08BA FOREIGN KEY (redeemed_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F7052F1D775834 ON ai_key (value)');
        $this->addSql('CREATE INDEX IDX_F7052F2FBC08BA ON ai_key (redeemed_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ai_key');
    }
}
