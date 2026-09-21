<?php

namespace App\Identity\Application;

use App\Identity\Domain\Repository\PasswordResetTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use Psr\Log\LoggerInterface;

/**
 * « Mot de passe oublié » : première étape, l'envoi du lien.
 *
 * Le contrôleur répond toujours la même chose, que l'adresse ait un compte ou non :
 * sinon, le formulaire dirait qui est inscrit sur la plateforme. C'est pour la même
 * raison que l'absence de compte n'est signalée que dans le journal d'audit.
 */
class RequestPasswordReset
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetTokenRepository $tokens,
        private readonly PasswordResetNotifier $notifier,
        private readonly LoggerInterface $auditLogger,
    ) {
    }

    /** Envoie un lien de réinitialisation si un compte porte cette adresse. */
    public function request(string $email): void
    {
        $user = $this->users->findOneByEmail($email);

        if (!$user) {
            $this->auditLogger->info('Réinitialisation demandée pour une adresse sans compte.');

            return;
        }

        $this->notifier->sendResetLink($user, $this->tokens->issue($user));
        $this->auditLogger->info('Lien de réinitialisation envoyé.', ['user_id' => $user->getId()]);
    }
}
