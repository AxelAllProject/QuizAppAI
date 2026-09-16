<?php

namespace App\Ai\Domain\Exception;

/**
 * Échec de la génération de quiz par IA : clé absente, quota Groq épuisé,
 * ou réponse du modèle inexploitable. Le message est destiné au professeur.
 */
class AiGenerationException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 502)
    {
        parent::__construct($message);
    }

    /** Code HTTP à renvoyer au navigateur. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
