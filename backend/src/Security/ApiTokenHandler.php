<?php

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Le navigateur envoie « Authorization: Bearer <jeton> » ; seul un jeton émis
 * à la connexion et non expiré ouvre une session. Le rôle vient de la base,
 * jamais de la requête.
 */
class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private readonly ApiTokenRepository $tokens)
    {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findValid($accessToken);

        if (null === $token) {
            throw new BadCredentialsException('Session expirée : reconnecte-toi.');
        }

        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
