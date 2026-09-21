<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;

/**
 * Les demandes de réinitialisation en cours disparaissent avec le compte : un lien
 * encore valable ne doit pas survivre à la personne qui l'a demandé.
 */
class PasswordResetPersonalData implements PersonalDataEraser
{
    public function __construct(private readonly PasswordResetTokenRepository $tokens)
    {
    }

    /** Supprime les jetons de réinitialisation du compte supprimé. */
    public function erase(User $user): void
    {
        $this->tokens->deleteByUser($user);
    }
}
