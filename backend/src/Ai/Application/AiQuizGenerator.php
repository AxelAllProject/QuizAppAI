<?php

namespace App\Ai\Application;

use App\Ai\Application\Dto\GenerateQuizInput;
use App\Ai\Domain\Exception\AiGenerationException;

/**
 * Rédige un brouillon de quiz à partir d'un sujet. Le brouillon n'est pas encore un
 * quiz : il passe ensuite par la même validation qu'une saisie faite dans l'éditeur.
 */
interface AiQuizGenerator
{
    /**
     * @return array{title: string, description: ?string, category: string, difficulty: string, questions: list<array{text: string, choices: list<string>, correctIndex: int, explanation: ?string}>}
     *
     * @throws AiGenerationException
     */
    public function generate(GenerateQuizInput $input): array;
}
