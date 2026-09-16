<?php

namespace App\Shared\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * En-têtes de sécurité ajoutés à chaque réponse de Symfony.
 *
 * - `X-Content-Type-Options: nosniff` : le navigateur respecte le type annoncé (un fichier
 *   déguisé en image ne sera jamais exécuté comme script) ;
 * - `Referrer-Policy: no-referrer` : aucune URL de l'API (PIN, identifiants) ne fuit vers un tiers ;
 * - sous /api, `Content-Security-Policy: default-src 'none'; frame-ancestors 'none'` et
 *   `X-Frame-Options: DENY` : une réponse JSON n'a rien à charger ni à afficher dans une iframe ;
 * - en HTTPS, `Strict-Transport-Security` : le navigateur refuse ensuite de repasser en HTTP.
 *
 * Les pages de Symfony hors /api (profiler en dev) gardent leur propre politique. Les fichiers de
 * public/uploads sont servis directement par le serveur web : voir docs/administration.md.
 */
#[AsEventListener(event: ResponseEvent::class, priority: -256)]
class SecurityHeadersListener
{
    /** Un an : la durée recommandée pour HSTS. */
    private const HSTS = 'max-age=31536000; includeSubDomains';

    /** Ajoute les en-têtes absents, sans écraser ceux qu'une réponse aurait déjà posés. */
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $event->getResponse()->headers;

        $wanted = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ];

        if (str_starts_with($request->getPathInfo(), '/api')) {
            $wanted['Content-Security-Policy'] = "default-src 'none'; frame-ancestors 'none'";
            $wanted['X-Frame-Options'] = 'DENY';
        }

        if ($request->isSecure()) {
            $wanted['Strict-Transport-Security'] = self::HSTS;
        }

        foreach ($wanted as $name => $value) {
            if (!$headers->has($name)) {
                $headers->set($name, $value);
            }
        }
    }
}
