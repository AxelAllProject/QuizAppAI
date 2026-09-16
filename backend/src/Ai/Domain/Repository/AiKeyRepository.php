<?php

namespace App\Ai\Domain\Repository;

use App\Ai\Domain\Model\AiKey;
use App\Identity\Domain\Model\User;

interface AiKeyRepository
{
    public function ofId(int $id): ?AiKey;

    public function findByValue(string $value): ?AiKey;

    /** La clé la plus récemment liée à ce compte qui a encore des générations disponibles. */
    public function findActiveFor(User $user): ?AiKey;

    /**
     * Listing du back-office : recherche libre (code, étiquette, détenteur) et filtre d'état.
     *
     * @return AiKey[]
     */
    public function search(?string $term = null, ?string $status = null): array;

    /** Efface le compte des clés qu'il a créées ou saisies ; les générations restantes ne sont pas rendues. */
    public function anonymizeUser(User $user, string $anonymous): void;

    public function add(AiKey $key): void;
}
