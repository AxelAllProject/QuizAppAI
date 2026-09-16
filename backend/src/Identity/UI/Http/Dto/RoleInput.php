<?php

namespace App\Identity\UI\Http\Dto;

use App\Identity\Domain\Model\User;
use Symfony\Component\Validator\Constraints as Assert;

/** Nouveau rôle choisi par un admin pour un compte. */
class RoleInput
{
    public function __construct(
        #[Assert\Choice(choices: User::ROLES, message: 'Rôle attendu : user, prof ou admin.')]
        public readonly string $role = User::ROLE_PLAYER,
    ) {
    }
}
