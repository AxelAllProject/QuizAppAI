<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\ApiToken;
use App\Identity\Domain\Model\User;

/** Accès aux jetons de connexion (implémenté avec Doctrine dans Infrastructure). */
interface ApiTokenRepository
{
    /** Crée un jeton et renvoie sa valeur en clair : c'est la seule fois où elle existe. */
    public function issue(User $user): string;

    /** Récupère le jeton correspondant à la valeur envoyée, s'il existe et n'a pas expiré. */
    public function findValid(#[\SensitiveParameter] string $plain): ?ApiToken;

    /** Supprime le jeton (déconnexion). */
    public function revoke(#[\SensitiveParameter] string $plain): void;

    /** Supprime les jetons expirés et renvoie leur nombre. */
    public function deleteExpired(): int;

    /** Supprime tous les jetons d'un compte. */
    public function deleteByUser(User $user): void;
}
