<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class LiveAnswerInput
{
    public function __construct(
        #[Assert\PositiveOrZero]
        public readonly int $choiceIndex = 0,
    ) {
    }
}
