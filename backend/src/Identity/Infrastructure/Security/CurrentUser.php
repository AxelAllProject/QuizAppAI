<?php

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Model\User;
use Symfony\Bundle\SecurityBundle\Security;

/** Accès pratique au compte connecté. Les droits propres à chaque contexte passent par des voters. */
class CurrentUser
{
    public function __construct(private readonly Security $security)
    {
    }

    public function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function name(): string
    {
        return $this->user()?->getUsername() ?? 'anonyme';
    }

    public function isAdmin(): bool
    {
        return $this->security->isGranted('ROLE_ADMIN');
    }
}
