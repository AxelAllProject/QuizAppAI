<?php

namespace App\Service;

use App\Entity\GameSession;
use App\Entity\LiveAnswer;
use App\Entity\LiveGame;
use App\Entity\LivePlayer;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\User;
use App\Exception\LiveGameException;
use App\Repository\LiveGameRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/**
 * Déroulé d'une partie en direct, façon Kahoot : l'animateur fait avancer les
 * questions, les joueurs répondent depuis leur appareil, la rapidité rapporte des points.
 *
 * Pas de WebSocket : chacun interroge l'état toutes les secondes, et les
 * transitions dues au temps (fin du chrono) sont appliquées à la lecture.
 */
class LiveGameEngine
{
    /** Bonne réponse instantanée ; une bonne réponse au dernier moment en rapporte la moitié. */
    public const MAX_POINTS = 1000;

    /** Tolérance accordée au réseau après la fin du chrono. */
    private const GRACE_MS = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LiveGameRepository $games,
        private readonly ClockInterface $clock,
    ) {
    }

    public function create(Quiz $quiz, User $host): LiveGame
    {
        do {
            $pin = sprintf('%06d', random_int(0, 999_999));
        } while ($this->games->isPinInUse($pin));

        $game = new LiveGame($pin, $quiz, $host);
        $this->em->persist($game);
        $this->em->flush();

        return $game;
    }

    public function join(LiveGame $game, User $user): LivePlayer
    {
        if ($player = $this->playerOf($game, $user)) {
            return $player;
        }

        if ($game->isFinished()) {
            throw new LiveGameException('Cette partie est terminée.');
        }

        if ($game->getHost()->getId() === $user->getId()) {
            throw new LiveGameException('Tu animes cette partie : tu ne peux pas y jouer.');
        }

        $player = new LivePlayer($game, $user);
        $game->addPlayer($player);
        $this->em->flush();

        return $player;
    }

    public function playerOf(LiveGame $game, User $user): ?LivePlayer
    {
        foreach ($game->getPlayers() as $player) {
            if ($player->getUser()->getId() === $user->getId()) {
                return $player;
            }
        }

        return null;
    }

    /** L'animateur passe à l'étape suivante : question → correction → question suivante… → podium. */
    public function advance(LiveGame $game): void
    {
        switch ($game->getStatus()) {
            case LiveGame::STATUS_LOBBY:
                if ($game->getPlayers()->isEmpty()) {
                    throw new LiveGameException('Attends qu’au moins un joueur ait rejoint la partie.');
                }
                $game->startQuestion(0, $this->nowMs());
                break;

            case LiveGame::STATUS_QUESTION:
                $game->reveal();
                break;

            case LiveGame::STATUS_REVEAL:
                $next = $game->getCurrentIndex() + 1;
                $next < $this->questionCount($game) ? $game->startQuestion($next, $this->nowMs()) : $this->finish($game);
                break;
        }

        $this->em->flush();
    }

    /** Arrêt par l'animateur : les questions déjà posées comptent dans l'historique des joueurs. */
    public function stop(LiveGame $game): void
    {
        if ($game->isFinished()) {
            return;
        }

        $game->getCurrentIndex() >= 0 ? $this->finish($game) : $game->finish($this->clock->now());
        $this->em->flush();
    }

    public function answer(LiveGame $game, LivePlayer $player, int $choiceIndex): LiveAnswer
    {
        $this->refresh($game);

        if (LiveGame::STATUS_QUESTION !== $game->getStatus()) {
            throw new LiveGameException('Trop tard : la question est close.');
        }

        $index = $game->getCurrentIndex();

        if (null !== $player->answerFor($index)) {
            throw new LiveGameException('Tu as déjà répondu à cette question.');
        }

        $question = $this->currentQuestion($game);

        if ($choiceIndex >= count($question->getChoices())) {
            throw new LiveGameException('Cette réponse n’existe pas.');
        }

        $elapsed = $this->elapsedMs($game);
        $correct = $choiceIndex === $question->getCorrectIndex();
        $answer = new LiveAnswer($player, $index, $choiceIndex, $correct, $correct ? $this->points($elapsed, $question->getTimeLimit()) : 0, $elapsed);
        $player->addAnswer($answer);

        // Inutile de faire patienter la salle quand tout le monde a répondu.
        if ($this->everyoneAnswered($game)) {
            $game->reveal();
        }

        $this->em->flush();

        return $answer;
    }

    /** Applique les transitions dues au temps : un chrono écoulé déclenche la correction. */
    public function refresh(LiveGame $game): void
    {
        $question = $this->currentQuestion($game);

        if (LiveGame::STATUS_QUESTION === $game->getStatus() && $this->elapsedMs($game) >= $question->getTimeLimit() * 1000 + self::GRACE_MS) {
            $game->reveal();
            $this->em->flush();
        }
    }

    public function points(int $elapsedMs, int $timeLimit): int
    {
        $ratio = min(1, max(0, $elapsedMs / ($timeLimit * 1000)));

        return (int) round(self::MAX_POINTS * (1 - $ratio / 2));
    }

    public function remainingMs(LiveGame $game): int
    {
        $question = $this->currentQuestion($game);

        if (LiveGame::STATUS_QUESTION !== $game->getStatus() || null === $question) {
            return 0;
        }

        return max(0, $question->getTimeLimit() * 1000 - $this->elapsedMs($game));
    }

    public function currentQuestion(LiveGame $game): ?Question
    {
        return $game->getQuiz()->getQuestions()->getValues()[$game->getCurrentIndex()] ?? null;
    }

    public function questionCount(LiveGame $game): int
    {
        return $game->getQuiz()->getQuestions()->count();
    }

    /** @return list<LivePlayer> du meilleur score au moins bon ; à égalité, le premier arrivé */
    public function ranking(LiveGame $game): array
    {
        $players = $game->getPlayers()->getValues();
        usort($players, static fn (LivePlayer $a, LivePlayer $b) => [$b->getScore(), $a->getId()] <=> [$a->getScore(), $b->getId()]);

        return $players;
    }

    private function everyoneAnswered(LiveGame $game): bool
    {
        foreach ($game->getPlayers() as $player) {
            if (null === $player->answerFor($game->getCurrentIndex())) {
                return false;
            }
        }

        return true;
    }

    private function elapsedMs(LiveGame $game): int
    {
        return max(0, $this->nowMs() - (int) $game->getQuestionStartedAtMs());
    }

    private function nowMs(): int
    {
        return (int) $this->clock->now()->format('Uv');
    }

    /** Fin de partie : chaque joueur retrouve la partie dans son historique, avec la correction. */
    private function finish(LiveGame $game): void
    {
        $quiz = $game->getQuiz();
        $asked = array_slice($quiz->getQuestions()->getValues(), 0, $game->getCurrentIndex() + 1);

        foreach ($game->getPlayers() as $player) {
            $details = [];

            foreach ($asked as $index => $question) {
                $answer = $player->answerFor($index);
                $details[] = [
                    'questionId' => $question->getId(),
                    'text' => $question->getText(),
                    'image' => $question->getImage(),
                    'choices' => $question->getChoices(),
                    'chosenIndex' => $answer?->getChoiceIndex(),
                    'correctIndex' => $question->getCorrectIndex(),
                    'correct' => (bool) $answer?->isCorrect(),
                    'explanation' => $question->getExplanation(),
                ];
            }

            $this->em->persist((new GameSession())
                ->setQuiz($quiz)
                ->setQuizTitle($quiz->getTitle())
                ->setPlayer($player->getNickname())
                ->setUser($player->getUser())
                ->setScore($player->getCorrectCount())
                ->setTotal(count($details))
                ->setAnswers($details));
        }

        $game->finish($this->clock->now());
    }
}
