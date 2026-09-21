<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\RequestPasswordReset;
use App\Identity\Application\ResetPassword;
use App\Identity\UI\Http\Dto\ForgotPasswordInput;
use App\Identity\UI\Http\Dto\ResetPasswordInput;
use App\Shared\UI\Http\ThrottlesRequests;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Mot de passe oublié », en deux temps : demander le lien, puis choisir le nouveau mot de passe.
 *
 * Les deux routes sont ouvertes sans jeton — c'est tout l'objet de la fonctionnalité — donc
 * toutes deux sont limitées par adresse IP : la première pour ne pas transformer QuizLab en
 * envoyeur d'e-mails, la seconde pour qu'un jeton de 64 caractères ne se devine pas par essais.
 */
class PasswordResetController extends AbstractController
{
    use ThrottlesRequests;

    public function __construct(
        private readonly RequestPasswordReset $requester,
        private readonly ResetPassword $resetter,
        #[Autowire(service: 'limiter.password_reset_requests')]
        private readonly RateLimiterFactory $requestsLimiter,
        #[Autowire(service: 'limiter.password_reset_attempts')]
        private readonly RateLimiterFactory $attemptsLimiter,
    ) {
    }

    /**
     * Demande l'envoi du lien. La réponse est la même que l'adresse ait un compte ou non :
     * ce formulaire ne doit pas servir à savoir qui est inscrit.
     */
    #[Route('/api/password-reset', name: 'api_password_reset_request', methods: ['POST'])]
    public function request(Request $request, #[MapRequestPayload] ForgotPasswordInput $input): JsonResponse
    {
        if ($refused = $this->throttle($this->requestsLimiter, (string) $request->getClientIp(), 'Trop de demandes : réessaie dans quelques minutes.')) {
            return $refused;
        }

        $this->requester->request($input->email);

        return $this->json(['message' => 'Si un compte existe avec cette adresse, un lien vient d’être envoyé.']);
    }

    /** Applique le nouveau mot de passe si le lien est encore valable. */
    #[Route('/api/password-reset/confirm', name: 'api_password_reset_confirm', methods: ['POST'])]
    public function confirm(Request $request, #[MapRequestPayload] ResetPasswordInput $input): JsonResponse
    {
        if ($refused = $this->throttle($this->attemptsLimiter, (string) $request->getClientIp(), 'Trop de tentatives : réessaie plus tard.')) {
            return $refused;
        }

        if (!$this->resetter->reset($input->token, $input->password)) {
            return $this->json(['error' => 'Ce lien n’est plus valable. Demandes-en un nouveau.'], 400);
        }

        return $this->json(['message' => 'Mot de passe changé. Tu peux te connecter.']);
    }
}
