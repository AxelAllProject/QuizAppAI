<?php

namespace App\Identity\Application;

use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;

/**
 * Jetons devenus inutiles : connexions expirées et liens de réinitialisation périmés
 * ou déjà consommés. Regroupés ici pour que PurgeExpiredData reste sous la limite
 * de quatre dépendances.
 */
class PurgeExpiredTokens
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokens,
        private readonly PasswordResetTokenRepository $resetTokens,
    ) {
    }

    /** @return array{tokens: int, passwordResets: int} ce qui a été supprimé */
    public function purge(): array
    {
        return [
            'tokens' => $this->apiTokens->deleteExpired(),
            'passwordResets' => $this->resetTokens->deleteExpired(),
        ];
    }
}
