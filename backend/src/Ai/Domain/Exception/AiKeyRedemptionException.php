<?php

namespace App\Ai\Domain\Exception;

/** Clé IA refusée à la saisie. Le message est destiné à la personne qui la saisit. */
class AiKeyRedemptionException extends \DomainException
{
    public function __construct(string $message, private readonly int $statusCode)
    {
        parent::__construct($message);
    }

    /** Code HTTP à renvoyer au navigateur. */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
