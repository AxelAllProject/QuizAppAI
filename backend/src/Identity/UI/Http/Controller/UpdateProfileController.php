<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\AccountNormalizer;
use App\Identity\Application\Exception\ProfileUpdateFailedException;
use App\Identity\Application\UpdateProfile;
use App\Identity\Domain\Model\User;
use App\Identity\UI\Http\Dto\UpdateProfileInput;
use App\Shared\UI\Http\ThrottlesRequests;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** « Mon compte » : modifier son e-mail, son pseudo ou son mot de passe. */
class UpdateProfileController extends AbstractController
{
    use ThrottlesRequests;

    public function __construct(
        private readonly UpdateProfile $profiles,
        private readonly AccountNormalizer $accounts,
        private readonly UserPasswordHasherInterface $hasher,
        #[Autowire(service: 'limiter.profile_update_failures')]
        private readonly RateLimiterFactory $failuresLimiter,
    ) {
    }

    /**
     * Même protection que la suppression de compte : avec un jeton volé, le mot de passe
     * demandé ici pourrait être deviné par essais successifs. Seuls les échecs comptent.
     */
    #[Route('/api/me', name: 'api_me_update', methods: ['PUT'])]
    public function __invoke(#[CurrentUser] User $user, #[MapRequestPayload] UpdateProfileInput $input): JsonResponse
    {
        if ($input->isEmpty()) {
            return $this->json(['error' => 'Aucune modification demandée.'], 400);
        }

        $limiter = $this->failuresLimiter->create((string) $user->getId());

        if (0 === ($limit = $limiter->consume(0))->getRemainingTokens()) {
            return $this->tooManyRequests('Trop de mots de passe incorrects : réessaie plus tard.', $limit);
        }

        if (!$this->hasher->isPasswordValid($user, $input->password)) {
            $limiter->consume();

            return $this->json(['error' => 'Mot de passe incorrect.'], 403);
        }

        try {
            $this->profiles->update($user, $input->email, $input->name, $input->newPassword);
        } catch (ProfileUpdateFailedException $exception) {
            return $this->json(['error' => $exception->getMessage(), 'errors' => $exception->getErrors()], 409);
        }

        return $this->json($this->accounts->me($user));
    }
}
