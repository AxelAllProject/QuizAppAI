<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\User;

interface UserRepository
{
    public function ofId(int $id): ?User;

    public function findOneByEmail(string $email): ?User;

    public function hasAdmin(): bool;

    /** « Axel » et « axel » ne peuvent pas coexister : on s'y tromperait dans un classement. */
    public function isUsernameTaken(string $username): bool;

    /**
     * Annuaire du back-office : recherche par pseudo et filtre par rôle.
     *
     * @return User[]
     */
    public function search(?string $term = null, ?string $role = null): array;

    /** @return User[] */
    public function findInactiveSince(\DateTimeImmutable $limit): array;

    public function add(User $user): void;

    public function remove(User $user): void;
}
