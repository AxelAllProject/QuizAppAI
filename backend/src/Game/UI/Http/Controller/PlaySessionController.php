<?php

namespace App\Game\UI\Http\Controller;

use App\Game\Application\PlaySession;
use App\Game\Application\SessionNormalizer;
use App\Game\UI\Http\Dto\PlayInput;
use App\Identity\Domain\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class PlaySessionController extends AbstractController
{
    public function __construct(
        private readonly PlaySession $playSession,
        private readonly SessionNormalizer $normalizer,
        #[Autowire(service: 'limiter.quiz_sessions')]
        private readonly RateLimiterFactory $quizSessionsLimiter,
    ) {
    }

    /**
     * Correction d'une partie : le front envoie ses choix, le serveur calcule le score.
     * La correction renvoyée contient les bonnes réponses : c'est pourquoi le classement
     * du quiz ne retient que la première partie de chaque joueur (voir findByQuiz).
     */
    #[Route('/api/quizzes/{id}/sessions', name: 'api_session_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function __invoke(int $id, #[MapRequestPayload] PlayInput $input, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->quizSessionsLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            return $this->json(['error' => 'Trop de parties enregistrées : réessaie dans quelques minutes.'], 429);
        }

        $session = $this->playSession->play($id, $input->answers, $user, $input->durationSeconds)
            ?? throw $this->createNotFoundException('Quiz introuvable.');

        return $this->json($this->normalizer->session($session), 201);
    }
}
