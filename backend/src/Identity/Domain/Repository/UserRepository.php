<?php

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Model\User;

/** Accès aux comptes utilisateurs (implémenté avec Doctrine dans Infrastructure). */
interface UserRepository
{
    /** Récupère un compte par son identifiant, ou null s'il n'existe pas. */
    public function ofId(int $id): ?User;

    /** Récupère un compte par son adresse e-mail (sans tenir compte des majuscules ni des espaces). */
    public function findOneByEmail(string $email): ?User;

    /** Indique s'il existe déjà au moins un administrateur (la clé de secours ne sert plus ensuite). */
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

    /** Prépare l'enregistrement d'un nouveau compte (écrit au prochain flush). */
    public function add(User $user): void;

    /** Prépare la suppression d'un compte (effective au prochain flush). */
    public function remove(User $user): void;
}
