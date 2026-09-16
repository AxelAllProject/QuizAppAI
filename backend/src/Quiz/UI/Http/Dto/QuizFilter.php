<?php

namespace App\Quiz\UI\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Filtres de la bibliothèque de quiz. */
class QuizFilter
{
    public function __construct(
        #[Assert\Length(max: 120)]
        public readonly ?string $search = null,

        #[Assert\Length(max: 60)]
        public readonly ?string $category = null,

        /** Seulement les quiz dont on est propriétaire. */
        public readonly bool $mine = false,
    ) {
    }
}
