<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\AccountNormalizer;
use App\Identity\Application\LoginUser;
use App\Identity\UI\Http\Dto\LoginInput;
use App\Shared\UI\Http\ThrottlesRequests;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends AbstractController
{
    use ThrottlesRequests;

    private const TOO_MANY_ATTEMPTS = 'Trop de tentatives de connexion : réessaie plus tard.';

    public function __construct(
        private readonly LoginUser $loginUser,
        private readonly AccountNormalizer $accounts,
        #[Autowire(service: 'limiter.login_attempts')]
        private readonly RateLimiterFactory $loginAttemptsLimiter,
        #[Autowire(service: 'limiter.login_failures_per_account')]
        private readonly RateLimiterFactory $loginFailuresPerAccountLimiter,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] LoginInput $input, Request $request): JsonResponse
    {
        if ($response = $this->throttle($this->loginAttemptsLimiter, $request->getClientIp() ?? 'inconnu', self::TOO_MANY_ATTEMPTS)) {
            return $response;
        }

        // La limite par IP ne suffit pas contre un attaquant qui change d'adresse à chaque essai :
        // chaque compte a aussi son compteur d'échecs. L'adresse est hachée pour ne pas se retrouver
        // en clair dans le cache du limiteur.
        $accountLimiter = $this->loginFailuresPerAccountLimiter->create(hash('sha256', mb_strtolower(trim($input->email))));

        if (0 === ($limit = $accountLimiter->consume(0))->getRemainingTokens()) {
            return $this->tooManyRequests(self::TOO_MANY_ATTEMPTS, $limit);
        }

        // Même message quand l'adresse est inconnue : on ne révèle pas quelles adresses ont un compte.
        if (!$session = $this->loginUser->login($input->email, $input->password)) {
            $accountLimiter->consume();

            return $this->json(['error' => 'E-mail ou mot de passe incorrect.'], 401);
        }

        $accountLimiter->reset();

        return $this->json(['token' => $session['token'], 'user' => $this->accounts->me($session['user'])]);
    }
}
