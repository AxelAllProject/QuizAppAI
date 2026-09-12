<?php

namespace App\Service;

use App\Dto\QuizInput;
use App\Entity\Question;
use App\Entity\Quiz;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Écrit un quiz à partir d'un QuizInput déjà validé — que la saisie vienne du
 * formulaire de l'éditeur ou d'un brouillon généré par l'IA, le résultat est
 * un quiz enregistré de la même façon, avec les mêmes règles.
 */
class QuizWriter
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function create(QuizInput $input, User $owner, string $author): Quiz
    {
        $quiz = (new Quiz())->setOwner($owner)->setAuthor($author);
        $this->hydrate($quiz, $input);

        $this->em->persist($quiz);
        $this->em->flush();

        return $quiz;
    }

    public function update(Quiz $quiz, QuizInput $input): Quiz
    {
        $this->hydrate($quiz, $input);
        $this->em->flush();

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

            $quiz->addQuestion($question);
            $this->em->persist($question);
        }
    }
}
