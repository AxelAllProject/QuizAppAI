<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\AccountExporter;
use App\Identity\Application\AccountNormalizer;
use App\Identity\Domain\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Mon compte » : consultation et export des données personnelles (suppression : DeleteAccountController). */
#[Route('/api/me')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountNormalizer $accounts,
        private readonly AccountExporter $exporter,
    ) {
    }

    /** Renvoie le compte connecté. */
    #[Route('', name: 'api_me', methods: ['GET'])]
    public function me(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json($this->accounts->me($user));
    }

    /** Télécharge toutes les données du compte en JSON. */
    #[Route('/export', name: 'api_me_export', methods: ['GET'])]
    public function export(#[CurrentUser] User $user): JsonResponse
    {
        $response = $this->json($this->exporter->export($user));
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', 'attachment; filename="quizlab-mes-donnees.json"');

        return $response;
    }
}
