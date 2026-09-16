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

    /** Exporte les quiz rédigés par le compte, avec leurs réponses. */
    public function export(User $user): array
    {
        return ['quizzes' => array_map(
            fn (Quiz $quiz) => $this->normalizer->detail($quiz, true),
            $this->quizzes->findByOwner($user),
        )];
    }

    /** Retire le propriétaire des quiz du compte supprimé et anonymise l'auteur affiché. */
    public function erase(User $user): void
    {
        $this->quizzes->anonymizeOwner($user, self::ANONYMOUS);
    }
}
