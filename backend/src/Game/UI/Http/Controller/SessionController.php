<?php

namespace App\Game\UI\Http\Controller;

use App\Game\Application\SessionNormalizer;
use App\Game\Domain\Model\GameSession;
use App\Game\Domain\Repository\GameSessionRepository;
use App\Game\Infrastructure\Security\GameSessionVoter;
use App\Game\UI\Http\Dto\HistoryFilter;
use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Repository\QuizRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Routes de lecture des parties solo : classement d'un quiz, historique, détail. */
#[Route('/api')]
class SessionController extends AbstractController
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly GameSessionRepository $sessions,
        private readonly SessionNormalizer $normalizer,
    ) {
    }

    /** Classement d'un quiz : visible par tous les joueurs connectés. */
    #[Route('/quizzes/{id}/sessions', name: 'api_quiz_sessions', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ranking(int $id): JsonResponse
    {
        $this->quizzes->ofId($id) ?? throw $this->createNotFoundException('Quiz introuvable.');

        return $this->json($this->summaries($this->sessions->findByQuiz($id)));
    }

    /** Historique : ses propres parties, ou toutes les parties pour un admin. */
    #[Route('/sessions', name: 'api_session_list', methods: ['GET'])]
    public function history(#[CurrentUser] User $user, #[MapQueryString] HistoryFilter $filter = new HistoryFilter()): JsonResponse
    {
        $all = $filter->all && $this->isGranted('ROLE_ADMIN');

        return $this->json($this->summaries($this->sessions->findHistory($all ? null : $user)));
    }

    /** Bilan du compte connecté sur toutes ses parties : l'historique ci-dessus n'en renvoie que 50. */
    #[Route('/sessions/summary', name: 'api_session_summary', methods: ['GET'])]
    public function summary(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->sessions->summaryFor($user));
    }

    /** Affiche le détail d'une partie, si elle appartient au compte connecté (ou pour un admin). */
    #[Route('/sessions/{id}', name: 'api_session_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $session = $this->sessions->ofId($id) ?? throw $this->createNotFoundException('Session introuvable.');

        if (!$this->isGranted(GameSessionVoter::VIEW, $session)) {
            return $this->json(['error' => 'Cette partie appartient à un autre joueur.'], 403);
        }

        return $this->json($this->normalizer->session($session));
    }

    /**
     * @param GameSession[] $sessions
     *
     * @return list<array<string, mixed>>
     */
    private function summaries(array $sessions): array
    {
        return array_map(fn (GameSession $session) => $this->normalizer->session($session, false), $sessions);
    }
}
