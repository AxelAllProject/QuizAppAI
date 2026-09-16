<?php

namespace App\Game\Application;

use App\Game\Domain\Model\GameSession;

class SessionNormalizer
{
    public function session(GameSession $session, bool $withDetail = true): array
    {
        $payload = [
            'id' => $session->getId(),
            'quizId' => $session->getQuiz()?->getId(),
            'quizTitle' => $session->getQuizTitle(),
            'player' => $session->getPlayer(),
            'score' => $session->getScore(),
            'total' => $session->getTotal(),
            'accuracy' => $session->getTotal() > 0 ? (int) round($session->getScore() / $session->getTotal() * 100) : 0,
            'durationSeconds' => $session->getDurationSeconds(),
            'playedAt' => $session->getPlayedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($withDetail) {
            $payload['answers'] = $session->getAnswers();
        }

        return $payload;
    }
}
