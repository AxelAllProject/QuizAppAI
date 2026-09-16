<?php

namespace App\Access\UI\Http\Controller;

use App\Access\Application\AccessKeyRedeemer;
use App\Access\UI\Http\Dto\RedeemKeyInput;
use App\Identity\Application\AccountNormalizer;
use App\Identity\Domain\Model\User;
use App\Shared\Application\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Mon compte » : saisir une clé d'accès pour obtenir le rôle prof ou admin. */
class RedeemAccessKeyController extends AbstractController
{
    public function __construct(
        private readonly AccessKeyRedeemer $redeemer,
        private readonly UnitOfWork $unitOfWork,
        private readonly AccountNormalizer $accounts,
    ) {
    }

    #[Route('/api/me/access-key', name: 'api_me_access_key', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RedeemKeyInput $input, #[CurrentUser] User $user): JsonResponse
    {
        if (!$this->redeemer->redeem($input->key, $user)) {
            return $this->json(['error' => "Clé d'accès invalide ou révoquée."], 403);
        }

        $this->unitOfWork->flush();

        return $this->json($this->accounts->me($user));
    }
}
