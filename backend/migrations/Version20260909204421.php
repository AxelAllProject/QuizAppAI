<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909204421 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_session (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quiz_title VARCHAR(180) NOT NULL, player VARCHAR(60) NOT NULL, score INTEGER NOT NULL, total INTEGER NOT NULL, duration_seconds INTEGER DEFAULT NULL, answers CLOB NOT NULL, played_at DATETIME NOT NULL, quiz_id INTEGER DEFAULT NULL, CONSTRAINT FK_4586AAFB853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_4586AAFB853CD175 ON game_session (quiz_id)');
        $this->addSql('CREATE TABLE question (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, text CLOB NOT NULL, choices CLOB NOT NULL, correct_index INTEGER NOT NULL, explanation CLOB DEFAULT NULL, position INTEGER NOT NULL, quiz_id INTEGER DEFAULT NULL, CONSTRAINT FK_B6F7494E853CD175 FOREIGN KEY (quiz_id) REFERENCES quiz (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B6F7494E853CD175 ON question (quiz_id)');
        $this->addSql('CREATE TABLE quiz (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(180) NOT NULL, description CLOB DEFAULT NULL, category VARCHAR(60) NOT NULL, difficulty VARCHAR(20) NOT NULL, author VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE question');
        $this->addSql('DROP TABLE quiz');
    }
}
