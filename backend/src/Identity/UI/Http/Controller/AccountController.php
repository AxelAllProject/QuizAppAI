<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\AccountEraser;
use App\Identity\Application\AccountExporter;
use App\Identity\Application\AccountNormalizer;
use App\Identity\Domain\Model\User;
use App\Identity\UI\Http\Dto\DeleteAccountInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Mon compte » : consultation, export et suppression des données personnelles. */
#[Route('/api/me')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly AccountNormalizer $accounts,
        private readonly AccountExporter $exporter,
        private readonly AccountEraser $eraser,
        private readonly UserPasswordHasherInterface $hasher,
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

    /** Supprime le compte connecté après vérification du mot de passe. */
    #[Route('', name: 'api_me_delete', methods: ['DELETE'])]
    public function delete(#[CurrentUser] User $user, #[MapRequestPayload] DeleteAccountInput $input): JsonResponse
    {
        if (!$this->hasher->isPasswordValid($user, $input->password)) {
            return $this->json(['error' => 'Mot de passe incorrect.'], 403);
        }

        $this->eraser->erase($user);

        return new JsonResponse(null, 204);
    }
}
