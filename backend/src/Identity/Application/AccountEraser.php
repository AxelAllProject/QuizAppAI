<?php

namespace App\Identity\Application;

use App\Access\Domain\Repository\AccessKeyRepository;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Game\Domain\Repository\GameSessionRepository;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Live\Domain\Repository\LiveGameRepository;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Shared\Application\UnitOfWork;

/**
 * Droit à l'effacement (RGPD, art. 17). Tout ce qui se rattache à la personne
 * disparaît : compte, jetons, historique, participations aux parties en direct.
 * Les quiz rédigés restent disponibles pour les autres, mais anonymisés.
 *
 * Ce cas d'usage traverse tous les contextes : chacun expose, via son repository,
 * ce qu'il faut effacer ou anonymiser quand un compte disparaît.
 */
class AccountEraser
{
    public const ANONYMOUS = 'compte supprimé';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly UserRepository $users,
        private readonly ApiTokenRepository $tokens,
        private readonly GameSessionRepository $sessions,
        private readonly QuizRepository $quizzes,
        private readonly AccessKeyRepository $accessKeys,
        private readonly AiKeyRepository $aiKeys,
        private readonly LiveGameRepository $liveGames,
    ) {
    }

    public function erase(User $user): void
    {
        $this->unitOfWork->transactional(function () use ($user): void {
            $this->sessions->deleteByUser($user);
            $this->tokens->deleteByUser($user);
            $this->quizzes->anonymizeOwner($user, self::ANONYMOUS);
            $this->accessKeys->anonymizeUser($user, self::ANONYMOUS);
            $this->aiKeys->anonymizeUser($user, self::ANONYMOUS);

            $this->liveGames->removeAll($this->liveGames->findByHost($user));

            foreach ($this->liveGames->findParticipations($user) as $participation) {
                $this->liveGames->removePlayer($participation);
            }

            $this->users->remove($user);
            $this->unitOfWork->flush();
        });
    }
}
