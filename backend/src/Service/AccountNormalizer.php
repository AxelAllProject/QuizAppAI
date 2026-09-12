<?php

namespace App\Service;

use App\Entity\AiKey;
use App\Entity\GameSession;
use App\Entity\LivePlayer;
use App\Entity\Quiz;
use App\Entity\User;
use App\Repository\AiKeyRepository;
use App\Repository\GameSessionRepository;
use App\Repository\LiveGameRepository;
use App\Repository\QuizRepository;

class AccountNormalizer
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly GameSessionRepository $sessions,
        private readonly LiveGameRepository $liveGames,
        private readonly AiKeyRepository $aiKeys,
        private readonly QuizNormalizer $quizNormalizer,
    ) {
    }

    public function me(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getUsername(),
            'role' => $user->getRole(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'consentedAt' => $user->getConsentedAt()?->format(\DateTimeInterface::ATOM),
            'privacyPolicyVersion' => $user->getConsentVersion(),
            'aiKey' => ($key = $this->aiKeys->findActiveFor($user)) ? $this->aiKey($key) : null,
        ];
    }

    private function aiKey(AiKey $key): array
    {
        return [
            'remainingGenerations' => $key->getRemainingGenerations(),
            'totalGenerations' => $key->getTotalGenerations(),
            'expiresAt' => $key->getExpiresAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /** Droit d'accès et à la portabilité (RGPD, art. 15 et 20) : tout ce qu'on sait de la personne, en JSON. */
    public function export(User $user): array
    {
        return [
            'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'account' => $this->me($user) + [
                'lastSeenAt' => $user->getLastSeenAt()->format(\DateTimeInterface::ATOM),
            ],
            'quizzes' => array_map(
                fn (Quiz $quiz) => $this->quizNormalizer->detail($quiz, true),
                $this->quizzes->findBy(['owner' => $user]),
            ),
            'sessions' => array_map(
                fn (GameSession $session) => $this->quizNormalizer->session($session),
                $this->sessions->findHistory($user, null),
            ),
            'liveGames' => array_map(static fn (LivePlayer $player) => [
                'pin' => $player->getGame()->getPin(),
                'quizTitle' => $player->getGame()->getQuiz()->getTitle(),
                'joinedAt' => $player->getJoinedAt()->format(\DateTimeInterface::ATOM),
                'nickname' => $player->getNickname(),
                'score' => $player->getScore(),
                'answers' => array_map(static fn ($answer) => [
                    'questionIndex' => $answer->getQuestionIndex(),
                    'choiceIndex' => $answer->getChoiceIndex(),
                    'correct' => $answer->isCorrect(),
                    'points' => $answer->getPoints(),
                    'elapsedMs' => $answer->getElapsedMs(),
                ], $player->getAnswers()->getValues()),
            ], $this->liveGames->findParticipations($user)),
        ];
    }
}
