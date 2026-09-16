<?php

namespace App\Quiz\Application;

use App\Quiz\Domain\Model\Quiz;

class QuizNormalizer
{
    /** Vue « carte » utilisée dans la bibliothèque de quiz. */
    public function summary(Quiz $quiz): array
    {
        return [
            'id' => $quiz->getId(),
            'title' => $quiz->getTitle(),
            'description' => $quiz->getDescription(),
            'category' => $quiz->getCategory(),
            'difficulty' => $quiz->getDifficulty(),
            'coverImage' => $quiz->getCoverImage(),
            'author' => $quiz->getAuthor(),
            'questionCount' => $quiz->getQuestions()->count(),
            'createdAt' => $quiz->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Vue détaillée. Les bonnes réponses ne sont incluses que pour l'auteur ou un admin
     * (mode édition) : en mode jeu, la correction se fait côté serveur.
     */
    public function detail(Quiz $quiz, bool $withAnswers): array
    {
        $questions = [];

        foreach ($quiz->getQuestions() as $question) {
            $payload = [
                'id' => $question->getId(),
                'text' => $question->getText(),
                'image' => $question->getImage(),
                'choices' => $question->getChoices(),
                'timeLimit' => $question->getTimeLimit(),
                'position' => $question->getPosition(),
            ];

            if ($withAnswers) {
                $payload['correctIndex'] = $question->getCorrectIndex();
                $payload['explanation'] = $question->getExplanation();
            }

            $questions[] = $payload;
        }

        return $this->summary($quiz) + ['questions' => $questions];
    }
}
