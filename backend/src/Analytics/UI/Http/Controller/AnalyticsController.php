<?php

namespace App\Analytics\UI\Http\Controller;

use App\Analytics\Application\AnalyticsReport;
use App\Analytics\UI\Http\Dto\AnalyticsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Tableau de bord des statistiques, réservé aux administrateurs. */
class AnalyticsController extends AbstractController
{
    public function __construct(private readonly AnalyticsReport $report)
    {
    }

    /** Audience, écrans, actions clés, génération IA et usage pédagogique sur la fenêtre demandée. */
    #[Route('/api/analytics', name: 'api_analytics', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(#[MapQueryString] AnalyticsFilter $filter = new AnalyticsFilter()): JsonResponse
    {
        return $this->json($this->report->forLastDays($filter->days));
    }
}
