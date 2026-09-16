<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\AccountEraser;
use App\Identity\Domain\Model\User;
use App\Identity\UI\Http\Dto\DeleteAccountInput;
use App\Shared\UI\Http\ThrottlesRequests;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Mon compte » : suppression définitive du compte, confirmée par le mot de passe. */
class DeleteAccountController extends AbstractController
{
    use ThrottlesRequests;

    public function __construct(
        private readonly AccountEraser $eraser,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire(service: 'limiter.account_deletion_failures')]
        private readonly RateLimiterFactory $deletionFailuresLimiter,
    ) {
    }

    /**
     * Avec un jeton volé, le mot de passe demandé ici pourrait être deviné par essais successifs :
     * au-delà de 5 échecs en 15 minutes, la route refuse même le bon mot de passe.
     */
    #[Route('/api/me', name: 'api_me_delete', methods: ['DELETE'])]
    public function __invoke(#[CurrentUser] User $user, #[MapRequestPayload] DeleteAccountInput $input): JsonResponse
    {
        $limiter = $this->deletionFailuresLimiter->create((string) $user->getId());

        if (0 === ($limit = $limiter->consume(0))->getRemainingTokens()) {
            return $this->tooManyRequests('Trop de mots de passe incorrects : réessaie plus tard.', $limit);
        }

        if (!$this->hasher->isPasswordValid($user, $input->password)) {
            $limiter->consume();

            return $this->json(['error' => 'Mot de passe incorrect.'], 403);
        }

        $this->eraser->erase($user);

        return new JsonResponse(null, 204);
    }
}
