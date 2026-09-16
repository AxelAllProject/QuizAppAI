<?php

namespace App\Live\Application;

use App\Identity\Domain\Model\User;
use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Repository\LiveGameRepository;
use App\Quiz\Domain\Model\Quiz;
use App\Shared\Application\UnitOfWork;
use Symfony\Component\Lock\LockFactory;

/** Ouvre une partie en direct avec un code PIN libre. */
class CreateLiveGame
{
    public function __construct(
        private readonly LiveGameRepository $games,
        private readonly UnitOfWork $unitOfWork,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function create(Quiz $quiz, User $host): LiveGame
    {
        // Un PIN peut resservir une fois sa partie terminée, donc pas de contrainte d'unicité en base :
        // le verrou empêche deux créations simultanées de tirer le même PIN libre.
        $lock = $this->lockFactory->createLock('live-game-pin');
        $lock->acquire(true);

        try {
            do {
                $pin = sprintf('%06d', random_int(0, 999_999));
            } while ($this->games->isPinInUse($pin));

            $game = new LiveGame($pin, $quiz, $host);
            $this->games->add($game);
            $this->unitOfWork->flush();
        } finally {
            $lock->release();
        }

        return $game;
    }
}
