<?php

namespace App\Access\UI\Http\Dto;

use App\Access\Domain\Model\AccessKey;
use Symfony\Component\Validator\Constraints as Assert;

/** Filtres du listing des clés d'accès dans le back-office. */
class AccessKeyFilter
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public readonly ?string $search = null,

        #[Assert\Choice(choices: AccessKey::ROLES, message: 'Rôle attendu : prof ou admin.')]
        public readonly ?string $role = null,

        #[Assert\Choice(choices: AccessKey::STATUSES, message: 'État inconnu.')]
        public readonly ?string $status = null,
    ) {
    }
}
