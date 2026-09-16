<?php

namespace App\Quiz\Infrastructure\DataFixtures;

use App\Identity\Domain\Model\User;
use App\Identity\Infrastructure\DataFixtures\UserFixtures;
use App\Quiz\Application\Dto\QuestionInput;
use App\Quiz\Application\Dto\QuizInput;
use App\Quiz\Application\QuizWriter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Bibliothèque de démonstration. Les quiz passent par QuizWriter, comme ceux de
 * l'éditeur : mêmes règles d'écriture, pas de chemin parallèle propre aux fixtures.
 */
class QuizFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function __construct(private readonly QuizWriter $writer)
    {
    }

    /** Nom de référence d'un quiz, pour le retrouver dans les autres fixtures. */
    public static function ref(string $slug): string
    {
        return 'quiz.'.$slug;
    }

    /** Crée les quiz de démonstration via QuizWriter. */
    public function load(ObjectManager $manager): void
    {
        foreach (self::quizzes() as $slug => $data) {
            $owner = $this->getReference(UserFixtures::ref($data['owner']), User::class);

            $input = new QuizInput(
                title: $data['title'],
                description: $data['description'],
                category: $data['category'],
                difficulty: $data['difficulty'],
                questions: array_map(
                    static fn (array $q) => new QuestionInput(text: $q[0], choices: $q[1], correctIndex: $q[2], explanation: $q[3] ?? null),
                    $data['questions'],
                ),
            );

            $this->addReference(self::ref($slug), $this->writer->create($input, $owner, $owner->getUsername()));
        }
    }

    /** @return array<string, array{owner: string, title: string, description: string, category: string, difficulty: string, questions: list<array>}> */
    private static function quizzes(): array
    {
        return [
            'culture' => [
                'owner' => 'mme.martin',
                'title' => 'Culture générale express',
                'description' => 'Dix minutes chrono pour faire le tour du monde et des idées.',
                'category' => 'Culture générale',
                'difficulty' => 'facile',
                'questions' => [
                    ['Quelle est la capitale de l\'Australie ?', ['Sydney', 'Canberra', 'Melbourne', 'Perth'], 1, 'Sydney est la plus grande ville, mais la capitale est Canberra depuis 1913.'],
                    ['Combien de continents compte la Terre ?', ['5', '6', '7', '8'], 2, 'Le découpage le plus courant en France en compte 6, le modèle anglo-saxon 7.'],
                    ['Qui a peint « La Nuit étoilée » ?', ['Claude Monet', 'Vincent van Gogh', 'Paul Cézanne', 'Edgar Degas'], 1],
                    ['Quel océan borde la côte ouest des États-Unis ?', ['Atlantique', 'Indien', 'Pacifique', 'Arctique'], 2],
                ],
            ],
            'web' => [
                'owner' => 'm.durand',
                'title' => 'Les bases du web',
                'description' => 'HTML, CSS, HTTP : les fondamentaux que tout développeur doit avoir.',
                'category' => 'Informatique',
                'difficulty' => 'moyen',
                'questions' => [
                    ['Que signifie HTTP ?', ['HyperText Transfer Protocol', 'High Transfer Text Protocol', 'Hyper Terminal Transfer Process', 'Home Transfer Protocol'], 0],
                    ['Quel code HTTP correspond à « ressource introuvable » ?', ['200', '301', '404', '500'], 2, '404 = Not Found. 500 signale une erreur côté serveur.'],
                    ['En CSS, quelle propriété gère l\'espace intérieur d\'un élément ?', ['margin', 'padding', 'border', 'gap'], 1],
                    ['Quelle balise HTML définit un lien ?', ['<link>', '<a>', '<href>', '<nav>'], 1],
                    ['Quel langage s\'exécute nativement dans le navigateur ?', ['PHP', 'Python', 'JavaScript', 'Ruby'], 2],
                ],
            ],
            'sciences' => [
                'owner' => 'mme.martin',
                'title' => 'Sciences & espace',
                'description' => 'Du tableau périodique aux confins du système solaire.',
                'category' => 'Sciences',
                'difficulty' => 'difficile',
                'questions' => [
                    ['Quel est le symbole chimique du potassium ?', ['P', 'Po', 'K', 'Pt'], 2, 'K vient du latin « kalium ».'],
                    ['Quelle planète possède le plus grand nombre de lunes connues ?', ['Jupiter', 'Saturne', 'Uranus', 'Neptune'], 1, 'Saturne est repassée devant Jupiter avec plus de 140 lunes confirmées.'],
                    ['Quelle est la vitesse de la lumière dans le vide (approx.) ?', ['150 000 km/s', '300 000 km/s', '1 080 km/s', '3 000 km/s'], 1],
                    ['Quel organite produit l\'énergie de la cellule ?', ['Le noyau', 'Le ribosome', 'La mitochondrie', 'Le lysosome'], 2],
                ],
            ],
            'histoire' => [
                'owner' => 'm.durand',
                'title' => 'Histoire de France',
                'description' => 'Des Gaulois à la Ve République, en quelques dates clés.',
                'category' => 'Histoire',
                'difficulty' => 'moyen',
                'questions' => [
                    ['En quelle année a eu lieu la prise de la Bastille ?', ['1776', '1789', '1815', '1848'], 1],
                    ['Qui a été sacré empereur des Français en 1804 ?', ['Louis XVI', 'Charlemagne', 'Napoléon Bonaparte', 'Louis-Philippe'], 2],
                    ['Quelle bataille oppose Vercingétorix à César en 52 av. J.-C. ?', ['Alésia', 'Gergovie', 'Poitiers', 'Bouvines'], 0, 'Gergovie est une victoire gauloise, mais la reddition a lieu à Alésia.'],
                    ['En quelle année la Ve République est-elle instaurée ?', ['1946', '1958', '1962', '1968'], 1],
                ],
            ],
        ];
    }

    /** Fixtures à charger avant celles-ci. */
    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    /** Groupes permettant de charger ces fixtures seules (--group=quizzes). */
    public static function getGroups(): array
    {
        return ['demo', 'quizzes'];
    }
}
