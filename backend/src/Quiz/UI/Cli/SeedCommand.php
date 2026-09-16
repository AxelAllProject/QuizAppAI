<?php

namespace App\Quiz\UI\Cli;

use App\Quiz\Domain\Model\Question;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Shared\Application\UnitOfWork;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:seed', description: 'Charge quelques quiz de démonstration')]
class SeedCommand extends Command
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly QuizRepository $quizzes,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        foreach ($this->demoQuizzes() as $data) {
            if ($this->quizzes->findOneByTitle($data['title'])) {
                $io->text(sprintf('« %s » existe déjà, ignoré.', $data['title']));
                continue;
            }

            $quiz = (new Quiz())
                ->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setCategory($data['category'])
                ->setDifficulty($data['difficulty'])
                ->setAuthor('admin');

            foreach ($data['questions'] as $position => $raw) {
                $question = (new Question())
                    ->setText($raw[0])
                    ->setChoices($raw[1])
                    ->setCorrectIndex($raw[2])
                    ->setExplanation($raw[3] ?? null)
                    ->setPosition($position);

                $quiz->addQuestion($question);
            }

            $this->quizzes->add($quiz);
            $io->text(sprintf('« %s » ajouté.', $data['title']));
        }

        $this->unitOfWork->flush();
        $io->success('Quiz de démonstration chargés.');

        return Command::SUCCESS;
    }

    private function demoQuizzes(): array
    {
        return [
            [
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
            [
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
            [
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
        ];
    }
}
