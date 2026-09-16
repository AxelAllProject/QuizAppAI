<?php

namespace App\Live\Application;

use App\Identity\Application\PersonalDataEraser;
use App\Identity\Application\PersonalDataExporter;
use App\Identity\Domain\Model\User;
use App\Live\Domain\Model\LiveAnswer;
use App\Live\Domain\Model\LivePlayer;
use App\Live\Domain\Repository\LiveGameRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** Participations aux parties en direct : exportées, puis supprimées avec les parties animées. */
#[AsTaggedItem(priority: 10)]
class LivePersonalData implements PersonalDataExporter, PersonalDataEraser
{
    public function __construct(private readonly LiveGameRepository $liveGames)
    {
    }

    /** Exporte les participations du compte aux parties en direct, avec ses réponses. */
    public function export(User $user): array
    {
        return ['liveGames' => array_map(static fn (LivePlayer $player) => [
            'pin' => $player->getGame()->getPin(),
            'quizTitle' => $player->getGame()->getQuiz()->getTitle(),
            'joinedAt' => $player->getJoinedAt()->format(\DateTimeInterface::ATOM),
            'nickname' => $player->getNickname(),
            'score' => $player->getScore(),
            'answers' => array_map(static fn (LiveAnswer $answer) => [
                'questionIndex' => $answer->getQuestionIndex(),
                'choiceIndex' => $answer->getChoiceIndex(),
                'correct' => $answer->isCorrect(),
                'points' => $answer->getPoints(),
                'elapsedMs' => $answer->getElapsedMs(),
            ], $player->getAnswers()->getValues()),
        ], $this->liveGames->findParticipations($user))];
    }

    /** Supprime les parties animées par le compte et ses participations aux autres. */
    public function erase(User $user): void
    {
        $this->liveGames->removeAll($this->liveGames->findByHost($user));

        foreach ($this->liveGames->findParticipations($user) as $participation) {
            $this->liveGames->removePlayer($participation);
        }
    }
}
