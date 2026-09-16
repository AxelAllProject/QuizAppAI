<?php

namespace App\Ai\UI\Http\Controller;

use App\Ai\Application\Dto\GenerateQuizInput;
use App\Ai\Application\GenerateQuiz;
use App\Ai\Domain\Exception\AiGenerationException;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Identity\Domain\Model\User;
use App\Quiz\Application\QuizNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fonctionnalité premium : avoir le rôle professeur ou administrateur ne suffit pas,
 * il faut en plus détenir une clé IA active (voir AiKey) — le rôle ouvre la porte,
 * la clé donne le nombre de générations.
 */
#[IsGranted('ROLE_TEACHER', message: 'Il faut être professeur ou administrateur pour créer un quiz par IA.')]
class AiController extends AbstractController
{
    public function __construct(
        private readonly GenerateQuiz $generateQuiz,
        private readonly AiKeyRepository $aiKeys,
        private readonly QuizNormalizer $normalizer,
        #[Autowire(service: 'limiter.ai_quiz_generation')]
        private readonly RateLimiterFactory $aiQuizGenerationLimiter,
    ) {
    }

    /** Vérifie la clé IA et la limite de débit, puis génère et publie le quiz. */
    #[Route('/api/ai/quizzes', name: 'api_ai_quiz_create', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] GenerateQuizInput $input, #[CurrentUser] User $user): JsonResponse
    {
        if (!$aiKey = $this->aiKeys->findActiveFor($user)) {
            return $this->json(['error' => 'Fonctionnalité premium : demande une clé IA à un administrateur, puis saisis-la dans « Mon compte ».'], 402);
        }

        $limit = $this->aiQuizGenerationLimiter->create((string) $user->getId())->consume();

        if (!$limit->isAccepted()) {
            $wait = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            return $this->json(['error' => sprintf('Trop de générations : réessaie dans %d minute(s).', (int) ceil($wait / 60))], 429);
        }

        try {
            $quiz = $this->generateQuiz->generate($input, $aiKey, $user);
        } catch (AiGenerationException $exception) {
            return $this->json(['error' => $exception->getMessage()], $exception->getStatusCode());
        }

        return $this->json($this->normalizer->detail($quiz, true) + ['canEdit' => true, 'aiGenerationsLeft' => $aiKey->getRemainingGenerations()], 201);
    }
}
