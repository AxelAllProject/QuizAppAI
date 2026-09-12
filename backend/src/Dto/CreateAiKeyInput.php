<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateAiKeyInput
{
    public function __construct(
        #[Assert\Range(min: 1, max: 1000, notInRangeMessage: 'Choisis entre {{ min }} et {{ max }} générations.')]
        public readonly int $totalGenerations = 5,

        #[Assert\Length(max: 120)]
        public readonly ?string $label = null,

        /** Durée de validité en jours ; null = pas d'expiration. */
        #[Assert\Range(min: 1, max: 365, notInRangeMessage: 'Choisis une expiration entre {{ min }} et {{ max }} jours.')]
        public readonly ?int $expiresInDays = null,

        /** Si renseigné, la clé est liée tout de suite à ce compte plutôt que de générer un code à partager. */
        #[Assert\Positive]
        public readonly ?int $userId = null,
    ) {
    }
}
