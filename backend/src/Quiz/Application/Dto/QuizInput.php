<?php

namespace App\Quiz\Application\Dto;

use App\Quiz\Domain\Model\QuizImage;
use Symfony\Component\Validator\Constraints as Assert;

/** Quiz saisi dans l'éditeur (ou généré par l'IA), avec ses règles de validation. */
class QuizInput
{
    /** Bornes de taille : sans elles, un seul envoi pourrait remplir la base (et chaque écran qui l'affiche). */
    public const MAX_QUESTIONS = 100;
    public const MAX_DESCRIPTION_LENGTH = 1000;

    public function __construct(
        #[Assert\Length(min: 3, max: 180, minMessage: 'Le titre doit faire au moins {{ limit }} caractères.')]
        public readonly string $title = '',

        #[Assert\Length(max: self::MAX_DESCRIPTION_LENGTH, maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères.')]
        public readonly ?string $description = null,

        #[Assert\Length(max: 60)]
        public readonly string $category = 'Général',

        #[Assert\Choice(choices: ['facile', 'moyen', 'difficile'], message: 'Difficulté attendue : facile, moyen ou difficile.')]
        public readonly string $difficulty = 'moyen',

        #[Assert\Regex(pattern: QuizImage::PATH_PATTERN, message: 'Image de couverture invalide : téléverse-la depuis l’éditeur.')]
        public readonly ?string $coverImage = null,

        /** @var list<QuestionInput> */
        #[Assert\Valid]
        #[Assert\Count(min: 1, max: self::MAX_QUESTIONS, minMessage: 'Ajoute au moins une question.', maxMessage: 'Pas plus de {{ limit }} questions par quiz.')]
        public readonly array $questions = [],
    ) {
    }
}
