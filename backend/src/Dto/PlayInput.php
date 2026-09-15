<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class PlayInput
{
    /**
     * @param array<int|string, int|null> $answers index du choix retenu, par identifiant de question
     */
    public function __construct(
        #[Assert\Count(max: 500)]
        public readonly array $answers = [],

        /** Durée annoncée par le navigateur : nulle ou négative, elle placerait n'importe qui en tête du classement. */
        #[Assert\Range(min: 1, max: 86400, notInRangeMessage: 'Durée de partie invalide.')]
        public readonly ?int $durationSeconds = null,
    ) {
    }
}
