<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260921114831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mesures d’usage : table du contexte Analytics';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE analytics_event (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, occurred_at DATETIME NOT NULL, role VARCHAR(20) NOT NULL, path VARCHAR(120) DEFAULT NULL, subject VARCHAR(60) DEFAULT NULL, outcome VARCHAR(20) DEFAULT NULL, duration_ms INTEGER DEFAULT NULL, name VARCHAR(40) NOT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_9CD0310AA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9CD0310A87C03D1B ON analytics_event (occurred_at)');
        $this->addSql('CREATE INDEX IDX_9CD0310A5E237E0687C03D1B ON analytics_event (name, occurred_at)');
        $this->addSql('CREATE INDEX IDX_9CD0310AA76ED395 ON analytics_event (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE analytics_event');
    }
}
