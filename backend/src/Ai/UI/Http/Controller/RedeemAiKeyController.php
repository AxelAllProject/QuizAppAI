<?php

namespace App\Ai\UI\Http\Controller;

use App\Ai\Application\AiKeyRedeemer;
use App\Ai\Domain\Exception\AiKeyRedemptionException;
use App\Ai\UI\Http\Dto\RedeemAiKeyInput;
use App\Identity\Application\AccountNormalizer;
use App\Identity\Domain\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Mon compte » : saisir une clé IA pour débloquer la génération de quiz. */
class RedeemAiKeyController extends AbstractController
{
    public function __construct(
        private readonly AiKeyRedeemer $redeemer,
        private readonly AccountNormalizer $accounts,
    ) {
    }

    /** Lie la clé IA saisie au compte connecté, puis renvoie le compte mis à jour. */
    #[Route('/api/me/ai-key', name: 'api_me_ai_key', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RedeemAiKeyInput $input, #[CurrentUser] User $user): JsonResponse
    {
        try {
            $this->redeemer->redeem($input->key, $user);
        } catch (AiKeyRedemptionException $exception) {
            return $this->json(['error' => $exception->getMessage()], $exception->getStatusCode());
        }

        return $this->json($this->accounts->me($user));
    }
}
