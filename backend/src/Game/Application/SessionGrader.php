<?php

namespace App\Game\Application;

use App\Game\Domain\Model\GameSession;
use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;

/**
 * Corrige une partie solo : le score est toujours calculé côté serveur, à partir des
 * choix envoyés. Utilisé par l'API et par les fixtures, qui produisent ainsi des
 * parties identiques à celles d'un vrai joueur.
 */
class SessionGrader
{
    /**
     * @param array<int|string, mixed> $given index de réponse choisi, par identifiant de question
     *
     * La session n'est pas persistée : c'est à l'appelant de l'enregistrer.
     */
    public function grade(Quiz $quiz, array $given, string $player, ?User $user, ?int $durationSeconds = null): GameSession
    {
        $score = 0;
        $details = [];

        foreach ($quiz->getQuestions() as $question) {
            $chosen = $given[(string) $question->getId()] ?? $given[$question->getId()] ?? null;
            $chosen = is_numeric($chosen) ? (int) $chosen : null;
            $correct = $chosen === $question->getCorrectIndex();
            $score += $correct ? 1 : 0;

            $details[] = [
                'questionId' => $question->getId(),
                'text' => $question->getText(),
                'image' => $question->getImage(),
                'choices' => $question->getChoices(),
                'chosenIndex' => $chosen,
                'correctIndex' => $question->getCorrectIndex(),
                'correct' => $correct,
                'explanation' => $question->getExplanation(),
            ];
        }

        return (new GameSession())
            ->setQuiz($quiz)
            ->setQuizTitle($quiz->getTitle())
            ->setPlayer($player)
            ->setUser($user)
            ->setScore($score)
            ->setTotal(count($details))
            ->setDurationSeconds($durationSeconds)
            ->setAnswers($details);
    }
}
