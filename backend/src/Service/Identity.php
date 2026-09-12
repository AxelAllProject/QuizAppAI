<?php

namespace App\Service;

use App\Entity\Quiz;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/** Accès pratique au compte connecté et aux règles de droits propres aux quiz. */
class Identity
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

    /** Professeurs et administrateurs rédigent les quiz ; les joueurs se contentent de jouer. */
    public function canCreate(): bool
    {
        return $this->security->isGranted('ROLE_TEACHER');
    }

    /** Un professeur ne retouche que ses propres quiz ; un admin, tous. */
    public function canEdit(Quiz $quiz): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $owner = $quiz->getOwner();

        return $this->canCreate() && null !== $owner && $owner->getId() === $this->user()?->getId();
    }
}
