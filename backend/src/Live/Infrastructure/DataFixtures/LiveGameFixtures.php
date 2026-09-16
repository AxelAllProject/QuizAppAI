<?php

namespace App\Live\Infrastructure\DataFixtures;

use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\DataFixtures\UserFixtures;
use App\Live\Application\CreateLiveGame;
use App\Live\Application\LiveGameEngine;
use App\Live\Domain\Model\LiveGame;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Infrastructure\DataFixtures\QuizFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Parties en direct jouées de bout en bout par LiveGameEngine, comme depuis l'écran
 * de l'animateur : une partie terminée (podium, historique des joueurs) et une
 * partie ouverte en salle d'attente, prête à être lancée.
 */
class LiveGameFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /** joueur => nombre de bonnes réponses sur le quiz « Culture générale express » */
    private const FINISHED_GAME_PLAYERS = ['lea' => 4, 'hugo' => 3, 'nina' => 2, 'tom' => 1];

    public function __construct(
        private readonly LiveGameEngine $engine,
        private readonly CreateLiveGame $createLiveGame,
    ) {
    }

    /** Crée une partie terminée et une partie en salle d'attente. */
    public function load(ObjectManager $manager): void
    {
        $this->playFinishedGame();
        $this->openLobby();
    }

    /** Joue une partie complète, question par question, jusqu'au podium. */
    private function playFinishedGame(): void
    {
        $game = $this->createLiveGame->create($this->quiz('culture'), $this->user('mme.martin'));
        $players = [];

        foreach (self::FINISHED_GAME_PLAYERS as $username => $correctCount) {
            $players[$username] = $this->engine->join($game, $this->user($username));
        }

        $this->engine->advance($game);

        while (LiveGame::STATUS_FINISHED !== $game->getStatus()) {
            $question = $this->engine->currentQuestion($game);

            foreach ($players as $username => $player) {
                $correct = $game->getCurrentIndex() < self::FINISHED_GAME_PLAYERS[$username];
                $choice = $correct ? $question->getCorrectIndex() : ($question->getCorrectIndex() + 1) % count($question->getChoices());
                $this->engine->answer($game, $player, $choice);
            }

            // Tout le monde a répondu : le moteur est passé à la correction, on enchaîne.
            $this->engine->advance($game);
        }
    }

    /** Ouvre une partie que des joueurs ont rejointe mais qui n'a pas commencé. */
    private function openLobby(): void
    {
        $game = $this->createLiveGame->create($this->quiz('web'), $this->user('m.durand'));

        foreach (['sarah', 'nina'] as $username) {
            $this->engine->join($game, $this->user($username));
        }
    }

    /** Récupère un quiz de démonstration par son nom court. */
    private function quiz(string $slug): Quiz
    {
        return $this->getReference(QuizFixtures::ref($slug), Quiz::class);
    }

    /** Récupère un compte de démonstration par son pseudo. */
    private function user(string $username): User
    {
        return $this->getReference(UserFixtures::ref($username), User::class);
    }

    /** Fixtures à charger avant celles-ci. */
    public function getDependencies(): array
    {
        return [UserFixtures::class, QuizFixtures::class];
    }

    /** Groupes permettant de charger ces fixtures seules (--group=live-games). */
    public static function getGroups(): array
    {
        return ['demo', 'live-games'];
    }
}
