<?php

namespace App\Access\Application;

use App\Access\Domain\Repository\AccessKeyRepository;
use App\Identity\Application\PersonalDataEraser;
use App\Identity\Domain\Model\User;

/** Les clés créées ou reçues par le compte restent dans l'historique, anonymisées. */
class AccessKeyPersonalData implements PersonalDataEraser
{
    public function __construct(private readonly AccessKeyRepository $keys)
    {
    }

    public function erase(User $user): void
    {
        $this->keys->anonymizeUser($user, self::ANONYMOUS);
    }
}
