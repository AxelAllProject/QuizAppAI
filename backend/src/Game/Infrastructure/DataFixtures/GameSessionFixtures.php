<?php

namespace App\Game\Infrastructure\DataFixtures;

use App\Game\Application\SessionGrader;
use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\DataFixtures\UserFixtures;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Infrastructure\DataFixtures\QuizFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/** Parties solo corrigées par SessionGrader : alimentent historiques, classements et tableau de bord. */
class GameSessionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    /**
     * [joueur, quiz, nombre de bonnes réponses, durée en secondes]. Les premières
     * réponses sont justes, les suivantes fausses : le score est donc prévisible.
     */
    private const SESSIONS = [
        ['lea', 'culture', 4, 42],
        ['lea', 'web', 4, 75],
        ['lea', 'histoire', 3, 51],
        ['hugo', 'culture', 3, 38],
        ['hugo', 'sciences', 2, 64],
        ['nina', 'web', 5, 58],
        ['nina', 'sciences', 4, 49],
        ['tom', 'culture', 1, 90],
        ['sarah', 'histoire', 4, 33],
        ['sarah', 'web', 2, 81],
    ];

    public function __construct(private readonly SessionGrader $grader)
    {
    }

    /** Joue et enregistre les parties de démonstration. */
    public function load(ObjectManager $manager): void
    {
        foreach (self::SESSIONS as [$username, $slug, $correctCount, $duration]) {
            $user = $this->getReference(UserFixtures::ref($username), User::class);
            $quiz = $this->getReference(QuizFixtures::ref($slug), Quiz::class);

            $manager->persist($this->grader->grade($quiz, $this->answers($quiz, $correctCount), $username, $user, $duration));
        }

        $manager->flush();
    }

    /** @return array<int, int> choix par identifiant de question */
    private function answers(Quiz $quiz, int $correctCount): array
    {
        $answers = [];

        foreach ($quiz->getQuestions()->getValues() as $position => $question) {
            $correct = $question->getCorrectIndex();
            $answers[$question->getId()] = $position < $correctCount ? $correct : ($correct + 1) % count($question->getChoices());
        }

        return $answers;
    }

    /** Fixtures à charger avant celles-ci. */
    public function getDependencies(): array
    {
        return [UserFixtures::class, QuizFixtures::class];
    }

    /** Groupes permettant de charger ces fixtures seules (--group=sessions). */
    public static function getGroups(): array
    {
        return ['demo', 'sessions'];
    }
}
