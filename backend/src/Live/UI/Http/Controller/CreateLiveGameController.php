<?php

namespace App\Live\UI\Http\Controller;

use App\Identity\Domain\Model\User;
use App\Live\Application\CreateLiveGame;
use App\Live\Application\LiveGameNormalizer;
use App\Live\UI\Http\Dto\LiveGameInput;
use App\Quiz\Domain\Repository\QuizRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** L'animateur crée la partie ; les joueurs la rejoignent ensuite avec le code PIN. */
class CreateLiveGameController extends AbstractController
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly CreateLiveGame $createLiveGame,
        private readonly LiveGameNormalizer $normalizer,
    ) {
    }

    #[Route('/api/live-games', name: 'api_live_create', methods: ['POST'])]
    #[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour animer une partie.')]
    public function __invoke(#[MapRequestPayload] LiveGameInput $input, #[CurrentUser] User $user): JsonResponse
    {
        $quiz = $this->quizzes->ofId($input->quizId) ?? throw $this->createNotFoundException('Quiz introuvable.');
        $game = $this->createLiveGame->create($quiz, $user);

        return $this->json($this->normalizer->state($game, null, true), 201);
    }
}
