<?php

namespace App\Dto;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

class RoleInput
{
    public function __construct(
        #[Assert\Choice(choices: User::ROLES, message: 'Rôle attendu : user, prof ou admin.')]
        public readonly string $role = User::ROLE_PLAYER,
    ) {
    }
}
