<?php

namespace App\Identity\UI\Http\Controller;

use App\Access\Application\AccessKeyRedeemer;
use App\Access\UI\Http\Dto\RedeemKeyInput;
use App\Ai\Domain\Repository\AiKeyRepository;
use App\Ai\UI\Http\Dto\RedeemAiKeyInput;
use App\Identity\Application\AccountEraser;
use App\Identity\Application\AccountNormalizer;
use App\Identity\Infrastructure\Security\CurrentUser;
use App\Identity\UI\Http\Dto\DeleteAccountInput;
use App\Shared\Application\UnitOfWork;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/** « Mon compte » : consultation, export et suppression des données personnelles. */
#[Route('/api/me')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly CurrentUser $identity,
        private readonly AccountNormalizer $accounts,
        private readonly AccessKeyRedeemer $redeemer,
        private readonly AiKeyRepository $aiKeys,
        private readonly AccountEraser $eraser,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UnitOfWork $unitOfWork,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        return $this->json($this->accounts->me($this->identity->user()));
    }

    #[Route('/access-key', name: 'api_me_access_key', methods: ['POST'])]
    public function redeemAccessKey(#[MapRequestPayload] RedeemKeyInput $input): JsonResponse
    {
        $user = $this->identity->user();

        if (!$this->redeemer->redeem($input->key, $user)) {
            return $this->json(['error' => "Clé d'accès invalide ou révoquée."], 403);
        }

        $this->unitOfWork->flush();

        return $this->json($this->accounts->me($user));
    }

    /**
     * Débloque la génération de quiz par IA : la clé se lie au premier compte qui la
     * saisit, et ne peut plus être utilisée par un autre après ça.
     */
    #[Route('/ai-key', name: 'api_me_ai_key', methods: ['POST'])]
    public function redeemAiKey(#[MapRequestPayload] RedeemAiKeyInput $input): JsonResponse
    {
        $user = $this->identity->user();
        $key = $this->aiKeys->findByValue($input->key);

        if (!$key || $key->isRevoked() || $key->isExpired($this->clock->now())) {
            return $this->json(['error' => 'Clé IA invalide, révoquée ou expirée.'], 403);
        }

        if ($key->isRedeemedBySomeoneElse($user)) {
            return $this->json(['error' => 'Cette clé IA a déjà été utilisée par quelqu’un d’autre.'], 409);
        }

        if ($key->isExhausted()) {
            return $this->json(['error' => 'Cette clé IA n’a plus de génération disponible.'], 409);
        }

        $key->redeemFor($user);
        $this->unitOfWork->flush();

        return $this->json($this->accounts->me($user));
    }

    #[Route('/export', name: 'api_me_export', methods: ['GET'])]
    public function export(): JsonResponse
    {
        $response = $this->json($this->accounts->export($this->identity->user()));
        $response->setEncodingOptions(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->headers->set('Content-Disposition', 'attachment; filename="quizlab-mes-donnees.json"');

        return $response;
    }

    #[Route('', name: 'api_me_delete', methods: ['DELETE'])]
    public function delete(#[MapRequestPayload] DeleteAccountInput $input): JsonResponse
    {
        $user = $this->identity->user();

        if (!$this->hasher->isPasswordValid($user, $input->password)) {
            return $this->json(['error' => 'Mot de passe incorrect.'], 403);
        }

        $this->eraser->erase($user);

        return new JsonResponse(null, 204);
    }
}
