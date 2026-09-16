<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\ApiToken;
use App\Identity\Domain\Model\User;

interface ApiTokenRepository
{
    /** Crée un jeton et renvoie sa valeur en clair : c'est la seule fois où elle existe. */
    public function issue(User $user): string;

    public function findValid(#[\SensitiveParameter] string $plain): ?ApiToken;

    public function revoke(#[\SensitiveParameter] string $plain): void;

    public function deleteExpired(): int;

    public function deleteByUser(User $user): void;
}
