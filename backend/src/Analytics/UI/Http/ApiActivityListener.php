<?php

namespace App\Analytics\UI\Http;

use App\Analytics\Application\RecordEvent;
use App\Analytics\Domain\Model\AnalyticsEvent;
use App\Identity\Domain\Model\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Enregistre les actions clés au passage des réponses de l'API, sur le modèle
 * d'AuditLogListener : une seule écoute, aucun service métier alourdi, et surtout
 * des chiffres que le navigateur ne peut pas inventer — contrairement aux pages
 * vues, qui, elles, viennent forcément du frontend.
 */
#[AsEventListener(event: ResponseEvent::class, priority: -512)]
class ApiActivityListener
{
    /** Route → nom d'événement. Ce qui n'y figure pas n'est pas mesuré. */
    private const TRACKED = [
        'api_register' => AnalyticsEvent::SIGNUP,
        'api_login' => AnalyticsEvent::LOGIN,
        'api_quiz_create' => AnalyticsEvent::QUIZ_CREATED,
        'api_session_create' => AnalyticsEvent::QUIZ_FINISHED,
        'api_live_create' => AnalyticsEvent::LIVE_HOSTED,
        'api_live_join' => AnalyticsEvent::LIVE_JOINED,
        'api_ai_quiz_create' => AnalyticsEvent::AI_GENERATION,
        'api_me_export' => AnalyticsEvent::RGPD_EXPORT,
    ];

    /** Routes dont on veut aussi mesurer les échecs, pour en calculer le taux de réussite. */
    private const OUTCOME_TRACKED = ['api_ai_quiz_create'];

    public function __construct(
        private readonly RecordEvent $recorder,
        private readonly Security $security,
    ) {
    }

    /** Traduit la réponse en événement mesuré, quand la route en fait partie. */
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $status = $event->getResponse()->getStatusCode();

        if (!$event->isMainRequest() || !isset(self::TRACKED[$route])) {
            return;
        }

        $tracksOutcome = in_array($route, self::OUTCOME_TRACKED, true);

        // Les routes sans issue ne se mesurent qu'en cas de succès : une inscription refusée
        // n'est pas une inscription. Celles qui en ont une mesurent aussi leurs échecs.
        if (!$tracksOutcome) {
            if ($status >= 400) {
                return;
            }
            $outcome = null;
        } elseif (null === $outcome = $this->outcomeFor($status)) {
            return;
        }

        $user = $this->security->getUser();

        // La mesure ne doit jamais faire échouer une requête qui, elle, a réussi : le compteur
        // d'une page peut se perdre, la réponse déjà calculée, non.
        try {
            $this->recorder->record(
                self::TRACKED[$route],
                $user instanceof User ? $user : null,
                subject: $this->subjectFor($request->attributes->get('_route_params', [])),
                outcome: $outcome,
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Issue d'une action qui peut échouer, ou null si la réponse ne dit rien de l'action
     * elle-même. Un refus de droits, un quota épuisé ou une limite de débit ne sont pas des
     * échecs de génération : les compter ferait chuter le taux de réussite de l'IA sans que
     * l'IA y soit pour quoi que ce soit. Seuls comptent la réussite, le conflit de clé (409)
     * et les erreurs du service (5xx, dont le 502 d'une réponse inexploitable).
     */
    private function outcomeFor(int $status): ?string
    {
        return match (true) {
            $status < 400 => AnalyticsEvent::OUTCOME_SUCCESS,
            409 === $status, $status >= 500 => AnalyticsEvent::OUTCOME_FAILURE,
            default => null,
        };
    }

    /** Identifiant de quiz porté par l'URL, quand il y en a un : sert au palmarès des quiz. */
    private function subjectFor(array $routeParams): ?string
    {
        return isset($routeParams['id']) ? (string) $routeParams['id'] : null;
    }
}
