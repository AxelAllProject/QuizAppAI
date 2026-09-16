<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Index sur les colonnes de tri et de purge. La diff générée pour SQLite recréait les
 * tables entières : de simples CREATE INDEX suffisent et ne touchent pas aux données.
 */
final class Version20260916211950 extends AbstractMigration
{
    private const INDEXES = [
        'IDX_7BA2F5EBF9D83E2' => 'api_token (expires_at)',
        'IDX_88BDF3E9B81C492A' => 'app_user (last_seen_at)',
        'IDX_4586AAFB39FA67F0' => 'game_session (played_at)',
        'IDX_FC5710FC8B8E8428' => 'live_game (created_at)',
        'IDX_A412FA928B8E8428' => 'quiz (created_at)',
        'IDX_A412FA9264C19C1' => 'quiz (category)',
    ];

    public function getDescription(): string
    {
        return 'Index de tri et de purge : jetons, comptes, parties, parties en direct, quiz';
    }

    public function up(Schema $schema): void
    {
        foreach (self::INDEXES as $name => $target) {
            $this->addSql(sprintf('CREATE INDEX %s ON %s', $name, $target));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_keys(self::INDEXES) as $name) {
            $this->addSql(sprintf('DROP INDEX %s', $name));
        }
    }
}
