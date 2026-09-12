<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910064828 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Comptes (e-mail + mot de passe), jetons de connexion, parties en direct, images et chrono des questions';
    }

    public function up(Schema $schema): void
    {
        // Les anciens pseudos (table player) n'avaient ni mot de passe ni e-mail : ils ne deviennent pas des comptes.
        $this->addSql('CREATE TABLE api_token (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, token_hash VARCHAR(64) NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_7BA2F5EBA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_7BA2F5EBB3BC57DA ON api_token (token_hash)');
        $this->addSql('CREATE INDEX IDX_7BA2F5EBA76ED395 ON api_token (user_id)');
        $this->addSql('CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(60) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, consented_at DATETIME DEFAULT NULL, consent_version VARCHAR(20) DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9E7927C74 ON app_user (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_88BDF3E9F85E0677 ON app_user (username)');
        $this->addSql('CREATE TABLE live_answer (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, question_index INTEGER NOT NULL, choice_index INTEGER NOT NULL, correct BOOLEAN NOT NULL, points INTEGER NOT NULL, elapsed_ms INTEGER NOT NULL, player_id INTEGER NOT NULL, CONSTRAINT FK_B98BFCF199E6F5DF FOREIGN KEY (player_id) REFERENCES live_player (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B98BFCF199E6F5DFB0987605 ON live_answer (player_id, question_index)');
        $this->addSql('CREATE INDEX IDX_B98BFCF199E6F5DF ON live_answer (player_id)');
        $this->addSql('CREATE TABLE live_game (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, status VARCHAR(20) NOT NULL, current_index INTEGER NOT NULL, question_started_at_ms BIGINT DEFAULT NULL, created_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, pin VARCHAR(6) NOT NULL, quiz_id INTEGER NOT NULL, host_id INTEGER NOT NULL, CONSTRAINT FK_FC5710FC853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FC5710FC1FB8D185 FOREIGN KEY (host_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FC5710FCB5852DF3 ON live_game (pin)');
        $this->addSql('CREATE INDEX IDX_FC5710FC853CD175 ON live_game (quiz_id)');
        $this->addSql('CREATE INDEX IDX_FC5710FC1FB8D185 ON live_game (host_id)');
        $this->addSql('CREATE TABLE live_player (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, nickname VARCHAR(60) NOT NULL, score INTEGER NOT NULL, joined_at DATETIME NOT NULL, game_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_FB4FCCB1E48FD905 FOREIGN KEY (game_id) REFERENCES live_game (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FB4FCCB1A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FB4FCCB1E48FD905A76ED395 ON live_player (game_id, user_id)');
        $this->addSql('CREATE INDEX IDX_FB4FCCB1E48FD905 ON live_player (game_id)');
        $this->addSql('CREATE INDEX IDX_FB4FCCB1A76ED395 ON live_player (user_id)');
        $this->addSql('DROP TABLE player');
        $this->addSql('CREATE TEMPORARY TABLE __temp__game_session AS SELECT id, quiz_title, player, score, total, duration_seconds, answers, played_at, quiz_id FROM game_session');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('CREATE TABLE game_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quiz_title VARCHAR(180) NOT NULL, player VARCHAR(60) NOT NULL, score INTEGER NOT NULL, total INTEGER NOT NULL, duration_seconds INTEGER DEFAULT NULL, answers CLOB NOT NULL, played_at DATETIME NOT NULL, quiz_id INTEGER DEFAULT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_4586AAFB853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_4586AAFBA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO game_session (id, quiz_title, player, score, total, duration_seconds, answers, played_at, quiz_id) SELECT id, quiz_title, player, score, total, duration_seconds, answers, played_at, quiz_id FROM __temp__game_session');
        $this->addSql('DROP TABLE __temp__game_session');
        $this->addSql('CREATE INDEX IDX_4586AAFB853CD175 ON game_session (quiz_id)');
        $this->addSql('CREATE INDEX IDX_4586AAFBA76ED395 ON game_session (user_id)');
        $this->addSql('ALTER TABLE question ADD COLUMN image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE question ADD COLUMN time_limit INTEGER DEFAULT 20 NOT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__quiz AS SELECT id, title, description, category, difficulty, author, created_at FROM quiz');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('CREATE TABLE quiz (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description CLOB DEFAULT NULL, category VARCHAR(60) NOT NULL, difficulty VARCHAR(20) NOT NULL, author VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, cover_image VARCHAR(255) DEFAULT NULL, owner_id INTEGER DEFAULT NULL, CONSTRAINT FK_A412FA927E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO quiz (id, title, description, category, difficulty, author, created_at) SELECT id, title, description, category, difficulty, author, created_at FROM __temp__quiz');
        $this->addSql('DROP TABLE __temp__quiz');
        $this->addSql('CREATE INDEX IDX_A412FA927E3C61F9 ON quiz (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE player (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(60) NOT NULL COLLATE "BINARY", role VARCHAR(20) NOT NULL COLLATE "BINARY", first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_98197A655E237E06 ON player (name)');
        $this->addSql('DROP TABLE api_token');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE live_answer');
        $this->addSql('DROP TABLE live_game');
        $this->addSql('DROP TABLE live_player');
        $this->addSql('CREATE TEMPORARY TABLE __temp__game_session AS SELECT id, quiz_title, player, score, total, duration_seconds, answers, played_at, quiz_id FROM game_session');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('CREATE TABLE game_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quiz_title VARCHAR(180) NOT NULL, player VARCHAR(60) NOT NULL, score INTEGER NOT NULL, total INTEGER NOT NULL, duration_seconds INTEGER DEFAULT NULL, answers CLOB NOT NULL, played_at DATETIME NOT NULL, quiz_id INTEGER DEFAULT NULL, CONSTRAINT FK_4586AAFB853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO game_session (id, quiz_title, player, score, total, duration_seconds, answers, played_at, quiz_id) SELECT id, quiz_title, player, score, total, duration_seconds, answers, played_at, quiz_id FROM __temp__game_session');
        $this->addSql('DROP TABLE __temp__game_session');
        $this->addSql('CREATE INDEX IDX_4586AAFB853CD175 ON game_session (quiz_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__question AS SELECT id, text, choices, correct_index, explanation, position, quiz_id FROM question');
        $this->addSql('DROP TABLE question');
        $this->addSql('CREATE TABLE question (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text CLOB NOT NULL, choices CLOB NOT NULL, correct_index INTEGER NOT NULL, explanation CLOB DEFAULT NULL, position INTEGER NOT NULL, quiz_id INTEGER DEFAULT NULL, CONSTRAINT FK_B6F7494E853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO question (id, text, choices, correct_index, explanation, position, quiz_id) SELECT id, text, choices, correct_index, explanation, position, quiz_id FROM __temp__question');
        $this->addSql('DROP TABLE __temp__question');
        $this->addSql('CREATE INDEX IDX_B6F7494E853CD175 ON question (quiz_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__quiz AS SELECT id, title, description, category, difficulty, author, created_at FROM quiz');
        $this->addSql('DROP TABLE quiz');
        $this->addSql('CREATE TABLE quiz (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description CLOB DEFAULT NULL, category VARCHAR(60) NOT NULL, difficulty VARCHAR(20) NOT NULL, author VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO quiz (id, title, description, category, difficulty, author, created_at) SELECT id, title, description, category, difficulty, author, created_at FROM __temp__quiz');
        $this->addSql('DROP TABLE __temp__quiz');
    }
}
