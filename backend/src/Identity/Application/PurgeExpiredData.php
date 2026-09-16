<?php

namespace App\Identity\Application;

use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Live\Application\LiveGamePurger;

/** Durées de conservation (RGPD, art. 5-1-e) annoncées dans la politique de confidentialité. */
class PurgeExpiredData
{
    public const LIVE_GAME_RETENTION = '-1 day';
    public const INACTIVE_ACCOUNT_RETENTION = '-3 years';

    public function __construct(
        private readonly ApiTokenRepository $tokens,
        private readonly UserRepository $users,
        private readonly AccountEraser $eraser,
        private readonly LiveGamePurger $liveGames,
    ) {
    }

    /** @return array{liveGames: int, inactiveAccounts: int} ce qui serait supprimé */
    public function preview(\DateTimeImmutable $now): array
    {
        return [
            'liveGames' => $this->liveGames->countCreatedBefore($now->modify(self::LIVE_GAME_RETENTION)),
            'inactiveAccounts' => count($this->users->findInactiveSince($now->modify(self::INACTIVE_ACCOUNT_RETENTION))),
        ];
    }

    /** @return array{tokens: int, liveGames: int, inactiveAccounts: int} ce qui a été supprimé */
    public function purge(\DateTimeImmutable $now): array
    {
        $inactive = $this->users->findInactiveSince($now->modify(self::INACTIVE_ACCOUNT_RETENTION));
        $result = [
            'tokens' => $this->tokens->deleteExpired(),
            'liveGames' => $this->liveGames->purgeCreatedBefore($now->modify(self::LIVE_GAME_RETENTION)),
            'inactiveAccounts' => count($inactive),
        ];

        foreach ($inactive as $user) {
            $this->eraser->erase($user);
        }

        return $result;
    }
}
