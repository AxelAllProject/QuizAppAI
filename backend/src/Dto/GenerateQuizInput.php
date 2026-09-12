<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class GenerateQuizInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'Décris le sujet du quiz à générer.')]
        #[Assert\Length(max: 200)]
        public readonly string $topic = '',

        #[Assert\Range(min: 1, max: 20, notInRangeMessage: 'Choisis entre {{ min }} et {{ max }} questions.')]
        public readonly int $questionCount = 5,

        #[Assert\Range(min: 2, max: 6, notInRangeMessage: 'Choisis entre {{ min }} et {{ max }} réponses par question.')]
        public readonly int $choiceCount = 4,

        #[Assert\Choice(choices: ['facile', 'moyen', 'difficile'], message: 'Difficulté attendue : facile, moyen ou difficile.')]
        public readonly string $difficulty = 'moyen',
    ) {
    }
}
