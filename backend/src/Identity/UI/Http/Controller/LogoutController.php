<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Domain\Repository\ApiTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/** Route de déconnexion. */
class LogoutController extends AbstractController
{
    public function __construct(private readonly ApiTokenRepository $tokens)
    {
    }

    /** Déconnexion : le jeton est détruit côté serveur, pas seulement oublié par le navigateur. */
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization');

        if (str_starts_with($header, 'Bearer ')) {
            $this->tokens->revoke(substr($header, 7));
        }

        return new JsonResponse(null, 204);
    }
}
