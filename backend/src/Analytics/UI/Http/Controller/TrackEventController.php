<?php

namespace App\Analytics\UI\Http\Controller;

use App\Analytics\Application\RecordEvent;
use App\Analytics\UI\Http\Dto\TrackEventInput;
use App\Identity\Domain\Model\User;
use App\Shared\UI\Http\ThrottlesRequests;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Collecte des pages vues, envoyées par le frontend.
 *
 * Ouverte aux visiteurs non connectés (la page d'accueil et la connexion se mesurent aussi),
 * donc limitée par adresse IP : cette route écrit en base sans demander de jeton, elle ne
 * doit pas devenir un moyen de faire grossir la table à volonté.
 *
 * Répond 204 en toutes circonstances côté client : une mesure n'a pas à faire apparaître
 * d'erreur à l'écran de quelqu'un qui consulte un quiz.
 */
class TrackEventController extends AbstractController
{
    use ThrottlesRequests;

    public function __construct(
        private readonly RecordEvent $recorder,
        #[Autowire(service: 'limiter.analytics_events')]
        private readonly RateLimiterFactory $limiter,
    ) {
    }

    /** Enregistre une page vue ou un quiz lancé. */
    #[Route('/api/events', name: 'api_event_track', methods: ['POST'])]
    public function __invoke(Request $request, #[MapRequestPayload] TrackEventInput $input, #[CurrentUser] ?User $user): JsonResponse
    {
        if ($this->throttle($this->limiter, (string) $request->getClientIp(), 'Trop d’événements.')) {
            return new JsonResponse(null, 204);
        }

        $this->recorder->record($input->name, $user, path: $input->path, subject: $input->subject, durationMs: $input->durationMs);

        return new JsonResponse(null, 204);
    }
}
