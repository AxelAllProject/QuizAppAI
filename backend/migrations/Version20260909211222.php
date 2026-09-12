<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260909211222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_key (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, value VARCHAR(40) NOT NULL, role VARCHAR(20) NOT NULL, label VARCHAR(120) DEFAULT NULL, created_by VARCHAR(60) NOT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, last_used_at DATETIME DEFAULT NULL, usage_count INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EAD0F67C1D775834 ON access_key (value)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE access_key');
    }
}
