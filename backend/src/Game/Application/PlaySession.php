<?php

namespace App\Game\Application;

use App\Game\Domain\Model\GameSession;
use App\Game\Domain\Repository\GameSessionRepository;
use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Shared\Application\UnitOfWork;

/** Enregistre une partie solo corrigée côté serveur. */
class PlaySession
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly SessionGrader $grader,
        private readonly GameSessionRepository $sessions,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * @param array<int|string, mixed> $answers index du choix retenu, par identifiant de question
     *
     * @return GameSession|null null si le quiz n'existe pas
     */
    public function play(int $quizId, array $answers, User $player, ?int $durationSeconds): ?GameSession
    {
        if (!$quiz = $this->quizzes->ofId($quizId)) {
            return null;
        }

        $session = $this->grader->grade($quiz, $answers, $player->getUsername(), $player, $durationSeconds);
        $this->sessions->add($session);
        $this->unitOfWork->flush();

        return $session;
    }
}
