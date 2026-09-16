<?php

namespace App\Game\UI\Http\Controller;

use App\Game\Application\SessionGrader;
use App\Game\Application\SessionNormalizer;
use App\Game\Domain\Model\GameSession;
use App\Game\Domain\Repository\GameSessionRepository;
use App\Game\UI\Http\Dto\PlayInput;
use App\Identity\Infrastructure\Security\CurrentUser;
use App\Quiz\Domain\Repository\QuizRepository;
use App\Shared\Application\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class SessionController extends AbstractController
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly GameSessionRepository $sessions,
        private readonly UnitOfWork $unitOfWork,
        private readonly CurrentUser $identity,
        private readonly SessionNormalizer $normalizer,
        private readonly SessionGrader $grader,
        #[Autowire(service: 'limiter.quiz_sessions')]
        private readonly RateLimiterFactory $quizSessionsLimiter,
    ) {
    }

    /**
     * Correction d'une partie : le front envoie ses choix, le serveur calcule le score.
     * La correction renvoyée contient les bonnes réponses : c'est pourquoi le classement
     * du quiz ne retient que la première partie de chaque joueur (voir findByQuiz).
     */
    #[Route('/quizzes/{id}/sessions', name: 'api_session_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function play(int $id, #[MapRequestPayload] PlayInput $input): JsonResponse
    {
        if (!$this->quizSessionsLimiter->create((string) $this->identity->user()->getId())->consume()->isAccepted()) {
            return $this->json(['error' => 'Trop de parties enregistrées : réessaie dans quelques minutes.'], 429);
        }

        $quiz = $this->quizzes->ofId($id);

        if (!$quiz) {
            return $this->json(['error' => 'Quiz introuvable.'], 404);
        }

        $session = $this->grader->grade($quiz, $input->answers, $this->identity->name(), $this->identity->user(), $input->durationSeconds);

        $this->sessions->add($session);
        $this->unitOfWork->flush();

        return $this->json($this->normalizer->session($session), 201);
    }

    /** Classement d'un quiz : visible par tous les joueurs connectés. */
    #[Route('/quizzes/{id}/sessions', name: 'api_quiz_sessions', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function ranking(int $id): JsonResponse
    {
        if (!$this->quizzes->ofId($id)) {
            return $this->json(['error' => 'Quiz introuvable.'], 404);
        }

        return $this->json(array_map(
            fn (GameSession $session) => $this->normalizer->session($session, false),
            $this->sessions->findByQuiz($id),
        ));
    }

    /** Historique : ses propres parties, ou toutes les parties pour un admin. */
    #[Route('/sessions', name: 'api_session_list', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        $all = $request->query->getBoolean('all') && $this->identity->isAdmin();
        $found = $this->sessions->findHistory($all ? null : $this->identity->user());

        return $this->json(array_map(
            fn (GameSession $session) => $this->normalizer->session($session, false),
            $found,
        ));
    }

    #[Route('/sessions/{id}', name: 'api_session_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $session = $this->sessions->ofId($id);

        if (!$session) {
            return $this->json(['error' => 'Session introuvable.'], 404);
        }

        if (!$this->identity->isAdmin() && $session->getUser()?->getId() !== $this->identity->user()->getId()) {
            return $this->json(['error' => 'Cette partie appartient à un autre joueur.'], 403);
        }

        return $this->json($this->normalizer->session($session));
    }

    #[Route('/stats', name: 'api_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $sessions = $this->sessions->findHistory(null, 500);
        $totalScore = array_sum(array_map(static fn (GameSession $s) => $s->getScore(), $sessions));
        $totalQuestions = array_sum(array_map(static fn (GameSession $s) => $s->getTotal(), $sessions));

        return $this->json([
            'quizCount' => $this->quizzes->countAll(),
            'sessionCount' => count($sessions),
            'playerCount' => count(array_unique(array_map(static fn (GameSession $s) => $s->getPlayer(), $sessions))),
            'globalAccuracy' => $totalQuestions > 0 ? (int) round($totalScore / $totalQuestions * 100) : 0,
            'leaderboard' => $this->sessions->leaderboard(),
        ]);
    }
}
