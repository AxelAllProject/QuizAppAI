<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;

/**
 * Envoi du lien de réinitialisation. Port : l'Application ne sait pas si c'est un e-mail,
 * un SMS ou rien du tout — l'adaptateur vit dans Infrastructure.
 */
interface PasswordResetNotifier
{
    /** Transmet à la personne le lien contenant le jeton en clair. */
    public function sendResetLink(User $user, #[\SensitiveParameter] string $token): void;
}
