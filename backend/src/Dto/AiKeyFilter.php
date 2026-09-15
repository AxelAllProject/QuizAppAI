<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Filtres du listing des clés IA dans le back-office. */
class AiKeyFilter
{
    public const STATUSES = ['active', 'unclaimed', 'exhausted', 'expired', 'revoked'];

    public function __construct(
        #[Assert\Length(max: 120)]
        public readonly ?string $search = null,

        #[Assert\Choice(choices: self::STATUSES, message: 'État inconnu.')]
        public readonly ?string $status = null,
    ) {
    }
}
