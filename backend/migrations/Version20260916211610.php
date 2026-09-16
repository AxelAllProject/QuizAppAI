<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Concurrence : une clé IA ne peut plus servir deux fois au même instant, et deux pseudos
 * qui ne diffèrent que par la casse ne peuvent plus coexister, même inscrits simultanément.
 */
final class Version20260916211610 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verrou optimiste sur les clés IA (ai_key.version) et pseudo canonique unique (app_user.username_canonical)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_key ADD COLUMN version INTEGER DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, email, username, password, role, created_at, last_seen_at, consented_at, consent_version FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(60) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, consented_at DATETIME DEFAULT NULL, consent_version VARCHAR(20) DEFAULT NULL, username_canonical VARCHAR(60) NOT NULL)');
        $this->addSql('INSERT INTO app_user (id, email, username, password, role, created_at, last_seen_at, consented_at, consent_version, username_canonical) SELECT id, email, username, password, role, created_at, last_seen_at, consented_at, consent_version, LOWER(username) FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');

        // LOWER() de SQLite ignore les lettres accentuées : la forme canonique définitive est calculée
        // en PHP, comme User::canonicalize(), avant de poser l'index unique.
        foreach ($this->connection->fetchAllAssociative('SELECT id, username FROM app_user') as $row) {
            $this->addSql('UPDATE app_user SET username_canonical = ? WHERE id = ?', [mb_strtolower(trim($row['username'])), $row['id']]);
        }

        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E992FC23A8 ON app_user (username_canonical)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__ai_key AS SELECT id, value, total_generations, remaining_generations, label, created_by, created_at, expires_at, revoked_at, redeemed_by_name, redeemed_at, redeemed_by_id FROM ai_key');
        $this->addSql('DROP TABLE ai_key');
        $this->addSql('CREATE TABLE ai_key (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value VARCHAR(40) NOT NULL, total_generations INTEGER NOT NULL, remaining_generations INTEGER NOT NULL, label VARCHAR(120) DEFAULT NULL, created_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, redeemed_by_name VARCHAR(60) DEFAULT NULL, redeemed_at DATETIME DEFAULT NULL, redeemed_by_id INTEGER DEFAULT NULL, CONSTRAINT FK_F7052F2FBC08BA FOREIGN KEY (redeemed_by_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO ai_key (id, value, total_generations, remaining_generations, label, created_by, created_at, expires_at, revoked_at, redeemed_by_name, redeemed_at, redeemed_by_id) SELECT id, value, total_generations, remaining_generations, label, created_by, created_at, expires_at, revoked_at, redeemed_by_name, redeemed_at, redeemed_by_id FROM __temp__ai_key');
        $this->addSql('DROP TABLE __temp__ai_key');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F7052F1D775834 ON ai_key (value)');
        $this->addSql('CREATE INDEX IDX_F7052F2FBC08BA ON ai_key (redeemed_by_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__app_user AS SELECT id, email, username, password, role, created_at, last_seen_at, consented_at, consent_version FROM app_user');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(60) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, consented_at DATETIME DEFAULT NULL, consent_version VARCHAR(20) DEFAULT NULL)');
        $this->addSql('INSERT INTO app_user (id, email, username, password, role, created_at, last_seen_at, consented_at, consent_version) SELECT id, email, username, password, role, created_at, last_seen_at, consented_at, consent_version FROM __temp__app_user');
        $this->addSql('DROP TABLE __temp__app_user');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
    }
}
