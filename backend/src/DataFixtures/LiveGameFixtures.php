<?php

namespace App\DataFixtures;

use App\Entity\LiveGame;
use App\Entity\Quiz;
use App\Entity\User;
use App\Service\LiveGameEngine;
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

    public function __construct(private readonly LiveGameEngine $engine)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->playFinishedGame();
        $this->openLobby();
    }

    private function playFinishedGame(): void
    {
        $game = $this->engine->create($this->quiz('culture'), $this->user('mme.martin'));
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

    private function openLobby(): void
    {
        $game = $this->engine->create($this->quiz('web'), $this->user('m.durand'));

        foreach (['sarah', 'nina'] as $username) {
            $this->engine->join($game, $this->user($username));
        }
    }

    private function quiz(string $slug): Quiz
    {
        return $this->getReference(QuizFixtures::ref($slug), Quiz::class);
    }

    private function user(string $username): User
    {
        return $this->getReference(UserFixtures::ref($username), User::class);
    }

    public function getDependencies(): array
    {
        return [UserFixtures::class, QuizFixtures::class];
    }

    public static function getGroups(): array
    {
        return ['demo', 'live-games'];
    }
}
