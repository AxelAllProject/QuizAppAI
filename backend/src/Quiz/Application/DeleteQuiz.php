<?php

namespace App\Quiz\Application;

use App\Game\Domain\Repository\GameSessionRepository;
use App\Live\Domain\Repository\LiveGameRepository;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Shared\Application\UnitOfWork;

class DeleteQuiz
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly GameSessionRepository $sessions,
        private readonly LiveGameRepository $liveGames,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** SQLite n'applique pas les clés étrangères : on détache l'historique et on supprime les parties en direct à la main. */
    public function delete(Quiz $quiz): void
    {
        $this->unitOfWork->transactional(function () use ($quiz): void {
            $this->sessions->detachQuiz($quiz);
            $this->liveGames->removeForQuiz($quiz);
            $this->quizzes->remove($quiz);
            $this->unitOfWork->flush();
        });
    }
}
