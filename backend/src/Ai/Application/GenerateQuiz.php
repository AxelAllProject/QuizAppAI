<?php

namespace App\Ai\Application;

use App\Ai\Application\Dto\GenerateQuizInput;
use App\Ai\Domain\Exception\AiGenerationException;
use App\Ai\Domain\Model\AiKey;
use App\Identity\Domain\Model\User;
use App\Quiz\Application\Dto\QuestionInput;
use App\Quiz\Application\Dto\QuizInput;
use App\Quiz\Application\QuizWriter;
use App\Quiz\Domain\Model\Quiz;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\Exception\ConcurrentModificationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Rédaction de quiz par IA : l'IA rédige les questions et le quiz est publié directement,
 * comme si le professeur avait rempli l'éditeur. La même validation que l'éditeur manuel
 * (QuizInput) s'applique avant d'écrire quoi que ce soit : un brouillon mal formé est
 * refusé plutôt que publié à moitié.
 */
class GenerateQuiz
{
    public function __construct(
        private readonly AiQuizGenerator $generator,
        private readonly ValidatorInterface $validator,
        private readonly QuizWriter $writer,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @throws AiGenerationException */
    public function generate(GenerateQuizInput $input, AiKey $key, User $author): Quiz
    {
        $quizInput = $this->toQuizInput($this->generator->generate($input));

        if (count($this->validator->validate($quizInput)) > 0) {
            throw new AiGenerationException('L’IA a renvoyé un quiz inexploitable : réessaie, ou reformule le sujet.', 502);
        }

        // Publier le quiz et décompter la génération réussissent ou échouent ensemble.
        try {
            return $this->unitOfWork->transactional(function () use ($quizInput, $key, $author): Quiz {
                $quiz = $this->writer->create($quizInput, $author, $author->getUsername());
                $key->consume();
                $this->unitOfWork->flush();

                return $quiz;
            });
        } catch (ConcurrentModificationException) {
            // Une autre génération a utilisé la clé pendant celle-ci : le quiz n'est pas publié.
            throw new AiGenerationException('Ta clé IA vient d’être utilisée par une autre génération : réessaie.', 409);
        }
    }

    /** @param array{title: string, description: ?string, category: string, difficulty: string, questions: list<array{text: string, choices: list<string>, correctIndex: int, explanation: ?string}>} $draft */
    private function toQuizInput(array $draft): QuizInput
    {
        return new QuizInput(
            title: $draft['title'],
            description: $draft['description'],
            category: $draft['category'],
            difficulty: $draft['difficulty'],
            questions: array_map(
                static fn (array $q) => new QuestionInput(text: $q['text'], choices: $q['choices'], correctIndex: $q['correctIndex'], explanation: $q['explanation']),
                $draft['questions'],
            ),
        );
    }
}
