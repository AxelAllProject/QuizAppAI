<?php

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Repository\ApiTokenRepository;
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

    /** Retrouve le compte à partir du jeton Bearer, ou refuse la requête si le jeton est invalide. */
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
