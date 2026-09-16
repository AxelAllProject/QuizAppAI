<?php

namespace App\Shared\UI\Http;

use App\Identity\Domain\Model\User;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Journal d'audit de l'API : qui a fait quoi, d'où, et avec quel résultat.
 *
 * Une seule écoute plutôt qu'un logger dans chaque service : les cas d'usage restent sous
 * la limite de 4 dépendances, et aucune action sensible ne peut être oubliée. Sont tracées
 * les écritures (POST, PUT, PATCH, DELETE) et toutes les réponses 401, 403 et 429, pour
 * repérer un brute force ou un balayage de codes PIN.
 *
 * Minimisation (RGPD) : ni corps de requête (mots de passe, clés), ni e-mail, ni jeton —
 * seulement l'identifiant du compte, l'adresse IP, la route et ses paramètres d'URL.
 */
#[AsEventListener(event: ResponseEvent::class, priority: -512)]
class AuditLogListener
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const WATCHED_STATUSES = [401, 403, 429];

    public function __construct(
        private readonly LoggerInterface $auditLogger,
        private readonly Security $security,
    ) {
    }

    /** Écrit une ligne d'audit pour chaque écriture ou refus sous /api. */
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $status = $event->getResponse()->getStatusCode();

        if (!$event->isMainRequest() || !str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        if (!in_array($request->getMethod(), self::WRITE_METHODS, true) && !in_array($status, self::WATCHED_STATUSES, true)) {
            return;
        }

        $route = (string) $request->attributes->get('_route', 'inconnue');
        $user = $this->security->getUser();

        $this->auditLogger->log(self::levelFor($status), sprintf('%s %s → %d', $request->getMethod(), $route, $status), [
            'route' => $route,
            'method' => $request->getMethod(),
            'status' => $status,
            'route_params' => $request->attributes->get('_route_params', []),
            'user_id' => $user instanceof User ? $user->getId() : null,
            'ip' => $request->getClientIp(),
        ]);
    }

    /** Erreur serveur : error ; refus ou limite atteinte : warning ; le reste : info. */
    private static function levelFor(int $status): string
    {
        return match (true) {
            $status >= 500 => LogLevel::ERROR,
            in_array($status, self::WATCHED_STATUSES, true) => LogLevel::WARNING,
            default => LogLevel::INFO,
        };
    }
}
