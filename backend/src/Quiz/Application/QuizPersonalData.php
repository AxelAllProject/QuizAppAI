<?php

namespace App\Quiz\Application;

use App\Identity\Application\PersonalDataEraser;
use App\Identity\Application\PersonalDataExporter;
use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;
use App\Quiz\Domain\Repository\QuizRepository;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** Quiz rédigés : exportés avec leurs réponses ; à l'effacement, ils restent jouables mais anonymisés. */
#[AsTaggedItem(priority: 30)]
class QuizPersonalData implements PersonalDataExporter, PersonalDataEraser
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly QuizNormalizer $normalizer,
    ) {
    }

    public function export(User $user): array
    {
        return ['quizzes' => array_map(
            fn (Quiz $quiz) => $this->normalizer->detail($quiz, true),
            $this->quizzes->findByOwner($user),
        )];
    }

    public function erase(User $user): void
    {
        $this->quizzes->anonymizeOwner($user, self::ANONYMOUS);
    }
}
