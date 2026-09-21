<?php

namespace App\Identity\Application;

use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;
use App\Shared\Application\UnitOfWork;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * « Mot de passe oublié » : seconde étape, le changement effectif.
 *
 * Toutes les sessions ouvertes sont fermées au passage : si quelqu'un d'autre était
 * connecté sur le compte (c'est souvent la raison du changement), il perd la main tout de suite.
 */
class ResetPassword
{
    public function __construct(
        private readonly PasswordResetTokenRepository $resetTokens,
        private readonly ApiTokenRepository $apiTokens,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @return bool false si le lien est inconnu, périmé ou déjà utilisé */
    public function reset(#[\SensitiveParameter] string $token, #[\SensitiveParameter] string $password): bool
    {
        if (!$resetToken = $this->resetTokens->findUsable($token)) {
            return false;
        }

        $user = $resetToken->getUser();

        $this->unitOfWork->transactional(function () use ($user, $resetToken, $password): void {
            $user->setPassword($this->hasher->hashPassword($user, $password));
            $resetToken->markUsed();
            $this->apiTokens->deleteByUser($user);
            $this->unitOfWork->flush();
        });

        return true;
    }
}
