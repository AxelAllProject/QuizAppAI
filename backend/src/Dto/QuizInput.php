<?php

namespace App\Dto;

use App\Service\ImageUploader;
use Symfony\Component\Validator\Constraints as Assert;

class QuizInput
{
    public function __construct(
        #[Assert\Length(min: 3, max: 180, minMessage: 'Le titre doit faire au moins {{ limit }} caractères.')]
        public readonly string $title = '',

        public readonly ?string $description = null,

        #[Assert\Length(max: 60)]
        public readonly string $category = 'Général',

        #[Assert\Choice(choices: ['facile', 'moyen', 'difficile'], message: 'Difficulté attendue : facile, moyen ou difficile.')]
        public readonly string $difficulty = 'moyen',

        #[Assert\Regex(pattern: ImageUploader::PATH_PATTERN, message: 'Image de couverture invalide : téléverse-la depuis l’éditeur.')]
        public readonly ?string $coverImage = null,

        /** @var list<QuestionInput> */
        #[Assert\Valid]
        #[Assert\Count(min: 1, minMessage: 'Ajoute au moins une question.')]
        public readonly array $questions = [],
    ) {
    }
}
