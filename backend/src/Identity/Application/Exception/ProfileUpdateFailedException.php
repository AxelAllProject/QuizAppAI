<?php

namespace App\Identity\Application\Exception;

/** Modification du profil refusée : chaque message est destiné à la personne qui remplit le formulaire. */
class ProfileUpdateFailedException extends \DomainException
{
    /** @param list<string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    /** @return list<string> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
