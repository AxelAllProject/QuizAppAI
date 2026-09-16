<?php

namespace App\Live\Application;

use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Model\LivePlayer;

/**
 * État d'une partie en direct, tel que le voit l'animateur ou un joueur.
 * La bonne réponse n'apparaît qu'une fois la question close.
 */
class LiveGameNormalizer
{
    /** Joueurs affichés entre deux questions. */
    private const LEADERBOARD_SIZE = 5;

    public function __construct(private readonly LiveGameEngine $engine)
    {
    }

    public function state(LiveGame $game, ?LivePlayer $me, bool $isHost): array
    {
        $status = $game->getStatus();
        $index = $game->getCurrentIndex();
        $question = $this->engine->currentQuestion($game);
        $players = $game->getPlayers()->getValues();

        $state = [
            'pin' => $game->getPin(),
            'status' => $status,
            'isHost' => $isHost,
            'host' => $game->getHost()->getUsername(),
            'quizId' => $game->getQuiz()->getId(),
            'quizTitle' => $game->getQuiz()->getTitle(),
            'coverImage' => $game->getQuiz()->getCoverImage(),
            'questionIndex' => $index,
            'questionCount' => $this->engine->questionCount($game),
            'players' => array_map(static fn (LivePlayer $player) => $player->getNickname(), $players),
        ];

        if (null !== $question && in_array($status, [LiveGame::STATUS_QUESTION, LiveGame::STATUS_REVEAL], true)) {
            $answers = array_filter(array_map(static fn (LivePlayer $player) => $player->answerFor($index), $players));

            $state['question'] = [
                'text' => $question->getText(),
                'image' => $question->getImage(),
                'choices' => $question->getChoices(),
                'timeLimit' => $question->getTimeLimit(),
            ];
            $state['remainingMs'] = $this->engine->remainingMs($game);
            $state['answeredCount'] = count($answers);

            if (LiveGame::STATUS_REVEAL === $status) {
                $distribution = array_fill(0, count($question->getChoices()), 0);

                foreach ($answers as $answer) {
                    ++$distribution[$answer->getChoiceIndex()];
                }

                $state['question']['correctIndex'] = $question->getCorrectIndex();
                $state['question']['explanation'] = $question->getExplanation();
                $state['distribution'] = $distribution;
            }
        }

        $ranking = $this->engine->ranking($game);

        if (in_array($status, [LiveGame::STATUS_REVEAL, LiveGame::STATUS_FINISHED], true)) {
            $rows = array_map(static fn (LivePlayer $player, int $position) => [
                'rank' => $position + 1,
                'nickname' => $player->getNickname(),
                'score' => $player->getScore(),
                'correctCount' => $player->getCorrectCount(),
                'lastPoints' => $player->answerFor($index)?->getPoints() ?? 0,
            ], $ranking, array_keys($ranking));

            $state['leaderboard'] = LiveGame::STATUS_REVEAL === $status ? array_slice($rows, 0, self::LEADERBOARD_SIZE) : $rows;
        }

        if (null !== $me) {
            $answer = $index >= 0 ? $me->answerFor($index) : null;
            $state['me'] = [
                'nickname' => $me->getNickname(),
                'score' => $me->getScore(),
                'rank' => array_search($me, $ranking, true) + 1,
                'answer' => null,
            ];

            if (null !== $answer) {
                // Pendant la question, on confirme le choix sans dire s'il est juste : ça se répéterait dans la salle.
                $state['me']['answer'] = LiveGame::STATUS_QUESTION === $status
                    ? ['choiceIndex' => $answer->getChoiceIndex()]
                    : ['choiceIndex' => $answer->getChoiceIndex(), 'correct' => $answer->isCorrect(), 'points' => $answer->getPoints()];
            }
        }

        return $state;
    }
}
