<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260910182651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Attribution directe d'une clé d'accès à un compte existant, choisi par l'administrateur";
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__access_key AS SELECT id, value, role, label, created_by, created_at, revoked_at, last_used_at, usage_count FROM access_key');
        $this->addSql('DROP TABLE access_key');
        $this->addSql('CREATE TABLE access_key (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value VARCHAR(40) NOT NULL, role VARCHAR(20) NOT NULL, label VARCHAR(120) DEFAULT NULL, created_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, usage_count INTEGER NOT NULL, assigned_to_name VARCHAR(60) DEFAULT NULL, assigned_to_id INTEGER DEFAULT NULL, CONSTRAINT FK_EAD0F67CF4BD7827 FOREIGN KEY (assigned_to_id) REFERENCES app_user (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO access_key (id, value, role, label, created_by, created_at, revoked_at, last_used_at, usage_count) SELECT id, value, role, label, created_by, created_at, revoked_at, last_used_at, usage_count FROM __temp__access_key');
        $this->addSql('DROP TABLE __temp__access_key');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAD0F67C1D775834 ON access_key (value)');
        $this->addSql('CREATE INDEX IDX_EAD0F67CF4BD7827 ON access_key (assigned_to_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__access_key AS SELECT id, value, role, label, created_by, created_at, revoked_at, last_used_at, usage_count FROM access_key');
        $this->addSql('DROP TABLE access_key');
        $this->addSql('CREATE TABLE access_key (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value VARCHAR(40) NOT NULL, role VARCHAR(20) NOT NULL, label VARCHAR(120) DEFAULT NULL, created_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, usage_count INTEGER NOT NULL)');
        $this->addSql('INSERT INTO access_key (id, value, role, label, created_by, created_at, revoked_at, last_used_at, usage_count) SELECT id, value, role, label, created_by, created_at, revoked_at, last_used_at, usage_count FROM __temp__access_key');
        $this->addSql('DROP TABLE __temp__access_key');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAD0F67C1D775834 ON access_key (value)');
    }
}
