<?php

namespace App\Access\Domain\Repository;

use App\Access\Domain\Model\AccessKey;
use App\Identity\Domain\Model\User;

interface AccessKeyRepository
{
    public function ofId(int $id): ?AccessKey;

    public function ofValue(string $value): ?AccessKey;

    /** Une clé révoquée — ou périmée — ne donne plus aucun droit. */
    public function findActive(string $value): ?AccessKey;

    /**
     * Réserve une clé à usage unique. Renvoie false si une autre requête l'a déjà réservée :
     * la garantie doit venir du stockage, même pour deux requêtes simultanées.
     */
    public function claim(AccessKey $key): bool;

    /** @return AccessKey[] */
    public function findAllOrdered(): array;

    /**
     * Listing du back-office : recherche libre (code, étiquette, bénéficiaire) et filtres rôle / état.
     *
     * @return AccessKey[]
     */
    public function search(?string $term = null, ?string $role = null, ?string $status = null): array;

    /** Efface le compte des clés qu'il a créées ou reçues, en gardant la trace de leur usage. */
    public function anonymizeUser(User $user, string $anonymous): void;

    public function add(AccessKey $key): void;
}
