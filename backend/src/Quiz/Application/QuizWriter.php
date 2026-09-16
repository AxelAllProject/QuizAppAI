<?php

namespace App\Quiz\Application;

use App\Identity\Domain\Model\User;
use App\Quiz\Application\Dto\QuizInput;
use App\Quiz\Domain\Model\Question;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Shared\Application\UnitOfWork;

/**
 * Écrit un quiz à partir d'un QuizInput déjà validé — que la saisie vienne du
 * formulaire de l'éditeur ou d'un brouillon généré par l'IA, le résultat est
 * un quiz enregistré de la même façon, avec les mêmes règles.
 */
class QuizWriter
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** Crée et enregistre un nouveau quiz pour son propriétaire. */
    public function create(QuizInput $input, User $owner, string $author): Quiz
    {
        $quiz = (new Quiz())->setOwner($owner)->setAuthor($author);
        $this->hydrate($quiz, $input);

        $this->quizzes->add($quiz);
        $this->unitOfWork->flush();

        return $quiz;
    }

    /** Met à jour un quiz existant et remplace ses questions. */
    public function update(Quiz $quiz, QuizInput $input): Quiz
    {
        $this->hydrate($quiz, $input);
        $this->unitOfWork->flush();

        return $quiz;
    }

    /**
     * Les questions sont entièrement remplacées à chaque enregistrement :
     * plus simple, et suffisant pour un éditeur de quiz.
     */
    private function hydrate(Quiz $quiz, QuizInput $input): void
    {
        $quiz->setTitle(trim($input->title))
            ->setDescription(trim((string) $input->description) ?: null)
            ->setCategory(trim($input->category) ?: 'Général')
            ->setDifficulty($input->difficulty)
            ->setCoverImage($input->coverImage);

        $quiz->clearQuestions();

        foreach (array_values($input->questions) as $position => $data) {
            $question = (new Question())
                ->setText(trim($data->text))
                ->setChoices(array_map(trim(...), $data->choices))
                ->setCorrectIndex($data->correctIndex)
                ->setExplanation(trim((string) $data->explanation) ?: null)
                ->setImage($data->image)
                ->setTimeLimit($data->timeLimit)
                ->setPosition($position);

            // Les questions suivent le quiz (cascade) : pas besoin de les enregistrer une à une.
            $quiz->addQuestion($question);
        }
    }
}
