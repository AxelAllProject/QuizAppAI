<?php

namespace App\Live\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Réponse choisie par un joueur pendant une partie en direct. */
class LiveAnswerInput
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public readonly int $choiceIndex = 0,
    ) {
    }
}
