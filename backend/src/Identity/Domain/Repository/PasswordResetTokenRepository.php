<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\PasswordResetToken;
use App\Identity\Domain\Model\User;

/** Accès aux jetons de réinitialisation de mot de passe (implémenté avec Doctrine dans Infrastructure). */
interface PasswordResetTokenRepository
{
    /** Crée un jeton et renvoie sa valeur en clair : c'est la seule fois où elle existe. */
    public function issue(User $user): string;

    /** Récupère le jeton correspondant à la valeur reçue, encore valable et jamais utilisé. */
    public function findUsable(#[\SensitiveParameter] string $plain): ?PasswordResetToken;

    /** Supprime les jetons expirés ou déjà utilisés, et renvoie leur nombre. */
    public function deleteExpired(): int;

    /** Supprime tous les jetons d'un compte. */
    public function deleteByUser(User $user): void;
}
