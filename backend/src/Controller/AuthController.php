<?php

namespace App\Controller;

use App\Dto\LoginInput;
use App\Dto\RegisterInput;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Service\AccessKeyRedeemer;
use App\Service\AccountNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ApiTokenRepository $tokens,
        private readonly AccessKeyRedeemer $redeemer,
        private readonly AccountNormalizer $accounts,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $em,
        #[Autowire(service: 'limiter.login_attempts')]
        private readonly RateLimiterFactory $loginAttemptsLimiter,
        #[Autowire(service: 'limiter.login_failures_per_account')]
        private readonly RateLimiterFactory $loginFailuresPerAccountLimiter,
        #[Autowire(service: 'limiter.registration_attempts')]
        private readonly RateLimiterFactory $registrationAttemptsLimiter,
    ) {
    }

    /** Inscription : e-mail, pseudo, mot de passe et consentement. Une clé d'accès donne en plus un rôle prof ou admin. */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(#[MapRequestPayload] RegisterInput $input, Request $request): JsonResponse
    {
        if ($response = $this->throttle($this->registrationAttemptsLimiter, $request, 'Trop de comptes créés depuis cette connexion : réessaie plus tard.')) {
            return $response;
        }

        $errors = [];

        if ($this->users->findOneByEmail($input->email)) {
            $errors[] = 'Un compte existe déjà avec cette adresse e-mail.';
        }

        if ($this->users->isUsernameTaken($input->name)) {
            $errors[] = 'Ce pseudo est déjà pris.';
        }

        $user = (new User())
            ->setEmail($input->email)
            ->setUsername($input->name)
            ->acceptPrivacyPolicy();

        // La clé n'est consommée qu'une fois le reste du formulaire valide.
        if ([] === $errors && '' !== trim((string) $input->accessKey) && !$this->redeemer->redeem((string) $input->accessKey, $user)) {
            $errors[] = "Clé d'accès invalide ou révoquée.";
        }

        if ([] !== $errors) {
            return $this->json(['error' => 'Le formulaire contient des erreurs.', 'errors' => $errors], 422);
        }

        $user->setPassword($this->hasher->hashPassword($user, $input->password));

        $this->em->persist($user);
        $this->em->flush();

        return $this->json(['token' => $this->tokens->issue($user), 'user' => $this->accounts->me($user)], 201);
    }

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(#[MapRequestPayload] LoginInput $input, Request $request): JsonResponse
    {
        if ($response = $this->throttle($this->loginAttemptsLimiter, $request, 'Trop de tentatives de connexion : réessaie plus tard.')) {
            return $response;
        }

        // La limite par IP ne suffit pas contre un attaquant qui change d'adresse à chaque essai :
        // chaque compte a aussi son compteur d'échecs. L'adresse est hachée pour ne pas se retrouver
        // en clair dans le cache du limiteur.
        $accountLimiter = $this->loginFailuresPerAccountLimiter->create(hash('sha256', mb_strtolower(trim($input->email))));

        if (0 === ($limit = $accountLimiter->consume(0))->getRemainingTokens()) {
            return $this->json(['error' => 'Trop de tentatives de connexion : réessaie plus tard.'], 429, $this->retryAfterHeader($limit));
        }

        $user = $this->users->findOneByEmail($input->email);

        if (!$user) {
            // Hachage pour rien, mais qui prend autant de temps que la vérification d'un vrai mot de
            // passe : sinon, la rapidité de la réponse révélerait quelles adresses ont un compte.
            $this->hasher->hashPassword(new User(), $input->password);
        }

        // Même message dans les deux cas : on ne révèle pas quelles adresses ont un compte.
        if (!$user || !$this->hasher->isPasswordValid($user, $input->password)) {
            $accountLimiter->consume();

            return $this->json(['error' => 'E-mail ou mot de passe incorrect.'], 401);
        }

        $accountLimiter->reset();

        if ($this->hasher->needsRehash($user)) {
            $user->setPassword($this->hasher->hashPassword($user, $input->password));
        }

        $user->touch();

        return $this->json(['token' => $this->tokens->issue($user), 'user' => $this->accounts->me($user)]);
    }

    /** Déconnexion : le jeton est détruit côté serveur, pas seulement oublié par le navigateur. */
    #[Route('/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization');

        if (str_starts_with($header, 'Bearer ')) {
            $this->tokens->revoke(substr($header, 7));
        }

        return new JsonResponse(null, 204);
    }

    /**
     * Limite par adresse IP : ces deux routes sont accessibles sans compte, donc sans autre
     * identifiant fiable à limiter. Renvoie une réponse 429 s'il faut refuser la requête.
     */
    private function throttle(RateLimiterFactory $limiter, Request $request, string $message): ?JsonResponse
    {
        $limit = $limiter->create($request->getClientIp() ?? 'inconnu')->consume();

        if ($limit->isAccepted()) {
            return null;
        }

        return $this->json(['error' => $message], 429, $this->retryAfterHeader($limit));
    }

    /** @return array<string, string> */
    private function retryAfterHeader(RateLimit $limit): array
    {
        return ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())];
    }
}
