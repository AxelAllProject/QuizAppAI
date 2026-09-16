<?php

namespace App\Shared\UI\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/** Réponses 429 homogènes pour les contrôleurs protégés par un limiteur. */
trait ThrottlesRequests
{
    /** Consomme un jeton du limiteur pour $key ; renvoie la réponse 429 s'il faut refuser la requête. */
    private function throttle(RateLimiterFactory $limiter, string $key, string $message): ?JsonResponse
    {
        $limit = $limiter->create($key)->consume();

        return $limit->isAccepted() ? null : $this->tooManyRequests($message, $limit);
    }

    /** Construit la réponse 429 avec l'en-tête Retry-After. */
    private function tooManyRequests(string $message, RateLimit $limit): JsonResponse
    {
        return new JsonResponse(['error' => $message], 429, ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())]);
    }
}
