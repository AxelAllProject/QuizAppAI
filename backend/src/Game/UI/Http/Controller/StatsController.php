<?php

namespace App\Game\UI\Http\Controller;

use App\Game\Domain\Repository\GameSessionRepository;
use App\Quiz\Domain\Repository\QuizRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class StatsController extends AbstractController
{
    public function __construct(
        private readonly QuizRepository $quizzes,
        private readonly GameSessionRepository $sessions,
    ) {
    }

    /** Compteurs du tableau de bord, agrégés en base sur toutes les parties. */
    #[Route('/api/stats', name: 'api_stats', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $stats = $this->sessions->globalStats();

        return $this->json([
            'quizCount' => $this->quizzes->countAll(),
            'sessionCount' => $stats['sessionCount'],
            'playerCount' => $stats['playerCount'],
            'globalAccuracy' => $stats['total'] > 0 ? (int) round($stats['score'] / $stats['total'] * 100) : 0,
            'leaderboard' => $this->sessions->leaderboard(),
        ]);
    }
}
