<?php

namespace App\Identity\UI\Http\Dto;

use App\Identity\Domain\Model\User;
use Symfony\Component\Validator\Constraints as Assert;

/** Filtres de l'annuaire des comptes dans le back-office. */
class UserFilter
{
    public function __construct(
        #[Assert\Length(max: 60)]
        public readonly ?string $search = null,

        #[Assert\Choice(choices: User::ROLES, message: 'Rôle inconnu.')]
        public readonly ?string $role = null,
    ) {
    }
}
