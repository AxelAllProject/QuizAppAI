<?php

namespace App\Ai\Application;

use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Application\PersonalDataEraser;
use App\Identity\Domain\Model\User;

/** Les clés IA créées ou saisies par le compte restent dans l'historique, anonymisées. */
class AiKeyPersonalData implements PersonalDataEraser
{
    public function __construct(private readonly AiKeyRepository $keys)
    {
    }

    /** Anonymise les clés IA créées ou saisies par le compte supprimé. */
    public function erase(User $user): void
    {
        $this->keys->anonymizeUser($user, self::ANONYMOUS);
    }
}
