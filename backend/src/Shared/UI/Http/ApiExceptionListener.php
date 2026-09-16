<?php

namespace App\Shared\UI\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Le front est une SPA : sous /api, toute exception doit ressortir en JSON
 * (et non dans la page d'erreur HTML de Symfony), avec le détail des
 * violations de validation quand il y en a.
 */
#[AsEventListener(event: ExceptionEvent::class)]
class ApiExceptionListener
{
    /** Transforme toute exception levée sous /api en réponse JSON. */
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
        $payload = ['error' => 500 === $status ? 'Erreur interne du serveur.' : $exception->getMessage()];

        if (($violations = $this->violationsOf($exception)) !== []) {
            $payload = ['error' => 'Le formulaire contient des erreurs.', 'errors' => $violations];
            $status = 422;
        }

        // Les en-têtes portés par l'exception (Retry-After d'une 429, Allow d'une 405…) sont conservés.
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $event->setResponse(new JsonResponse($payload, $status, $headers));
    }

    /** @return list<string> */
    private function violationsOf(\Throwable $exception): array
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof ValidationFailedException) {
            return [];
        }

        $messages = [];

        foreach ($previous->getViolations() as $violation) {
            $path = $violation->getPropertyPath();
            $messages[] = '' !== $path ? sprintf('%s : %s', $path, $violation->getMessage()) : $violation->getMessage();
        }

        return $messages;
    }
}
