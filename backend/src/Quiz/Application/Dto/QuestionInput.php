<?php

namespace App\Quiz\Application\Dto;

use App\Quiz\Domain\Model\QuizImage;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class QuestionInput
{
    public function __construct(
        #[Assert\NotBlank(message: 'L\'intitulé de la question est vide.')]
        public readonly string $text = '',

        /** @var list<string> */
        #[Assert\Count(min: 2, max: 6, minMessage: 'Il faut au moins {{ limit }} réponses.', maxMessage: 'Pas plus de {{ limit }} réponses.')]
        #[Assert\All([new Assert\NotBlank(message: 'Une réponse est vide.')])]
        public readonly array $choices = [],

        public readonly int $correctIndex = 0,

        public readonly ?string $explanation = null,

        #[Assert\Regex(pattern: QuizImage::PATH_PATTERN, message: 'Image invalide : téléverse-la depuis l’éditeur.')]
        public readonly ?string $image = null,

        #[Assert\Range(min: 5, max: 240, notInRangeMessage: 'Le chrono doit être compris entre {{ min }} et {{ max }} secondes.')]
        public readonly int $timeLimit = 20,
    ) {
    }

    #[Assert\Callback]
    public function validateCorrectIndex(ExecutionContextInterface $context): void
    {
        if ($this->correctIndex < 0 || $this->correctIndex >= count($this->choices)) {
            $context->buildViolation('Sélectionne la bonne réponse.')
                ->atPath('correctIndex')
                ->addViolation();
        }
    }
}
