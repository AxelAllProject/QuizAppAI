<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class LiveGameInput
{
    public function __construct(
        #[Assert\Positive(message: 'Choisis un quiz.')]
        public readonly int $quizId = 0,
    ) {
    }
}
