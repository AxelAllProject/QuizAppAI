<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\AccountNormalizer;
use App\Identity\Application\Exception\RegistrationFailedException;
use App\Identity\Application\RegisterUser;
use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\UI\Http\Dto\RegisterInput;
use App\Shared\UI\Http\ThrottlesRequests;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class RegisterController extends AbstractController
{
    use ThrottlesRequests;

    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly ApiTokenRepository $tokens,
        private readonly AccountNormalizer $accounts,
        #[Autowire(service: 'limiter.registration_attempts')]
        private readonly RateLimiterFactory $registrationAttemptsLimiter,
    ) {
    }

    /** Inscription : e-mail, pseudo, mot de passe et consentement. Une clé d'accès donne en plus un rôle prof ou admin. */
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterInput $input, Request $request): JsonResponse
    {
        // Limite par adresse IP : la route est accessible sans compte, donc sans autre identifiant fiable.
        if ($response = $this->throttle($this->registrationAttemptsLimiter, $request->getClientIp() ?? 'inconnu', 'Trop de comptes créés depuis cette connexion : réessaie plus tard.')) {
            return $response;
        }

        try {
            $user = $this->registerUser->register($input->email, $input->name, $input->password, $input->accessKey);
        } catch (RegistrationFailedException $exception) {
            return $this->json(['error' => 'Le formulaire contient des erreurs.', 'errors' => $exception->getErrors()], 422);
        }

        return $this->json(['token' => $this->tokens->issue($user), 'user' => $this->accounts->me($user)], 201);
    }
}
