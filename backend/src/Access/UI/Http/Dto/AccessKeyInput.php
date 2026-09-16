<?php

namespace App\Access\UI\Http\Dto;

use App\Access\Domain\Model\AccessKey;
use Symfony\Component\Validator\Constraints as Assert;

class AccessKeyInput
{
    public function __construct(
        #[Assert\Choice(choices: AccessKey::ROLES, message: 'Rôle attendu : prof ou admin.')]
        public readonly string $role = AccessKey::ROLE_TEACHER,

        #[Assert\Length(max: 120)]
        public readonly ?string $label = null,

        /** Si renseigné, le rôle est accordé tout de suite à ce compte plutôt que de générer un code à partager. */
        #[Assert\Positive]
        public readonly ?int $userId = null,

        /** Durée de validité du code en jours ; null = pas d'expiration. */
        #[Assert\Range(min: 1, max: 365, notInRangeMessage: 'Choisis une expiration entre {{ min }} et {{ max }} jours.')]
        public readonly ?int $expiresInDays = null,
    ) {
    }
}
