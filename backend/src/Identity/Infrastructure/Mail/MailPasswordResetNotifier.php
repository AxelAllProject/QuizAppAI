<?php

namespace App\Identity\Infrastructure\Mail;

use App\Identity\Application\PasswordResetNotifier;
use App\Identity\Domain\Model\PasswordResetToken;
use App\Identity\Domain\Model\User;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoie le lien de réinitialisation par e-mail. Le lien pointe vers le frontend,
 * qui présente le formulaire et repasse le jeton à l'API.
 *
 * Sans MAILER_DSN réel (`null://null` par défaut), le message est fabriqué puis jeté :
 * la fonctionnalité ne casse pas, elle ne sert simplement à rien tant qu'un SMTP n'est pas configuré.
 */
#[AsAlias(PasswordResetNotifier::class)]
class MailPasswordResetNotifier implements PasswordResetNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(APP_FRONTEND_URL)%')]
        private readonly string $frontendUrl,
        #[Autowire('%env(APP_MAIL_FROM)%')]
        private readonly string $from,
    ) {
    }

    /** Compose et envoie l'e-mail contenant le lien à usage unique. */
    public function sendResetLink(User $user, #[\SensitiveParameter] string $token): void
    {
        $link = sprintf('%s/mot-de-passe/%s', rtrim($this->frontendUrl, '/'), $token);
        $validity = (new \DateTimeImmutable())->diff(new \DateTimeImmutable(PasswordResetToken::LIFETIME))->h;

        $this->mailer->send(
            (new Email())
                ->from($this->from)
                ->to($user->getEmail())
                ->subject('QuizLab — réinitialiser ton mot de passe')
                ->text($this->body($user->getUsername(), $link, $validity))
        );
    }

    /** Texte brut volontairement : pas d'images ni de traqueur dans un e-mail envoyé à des élèves. */
    private function body(string $name, string $link, int $validity): string
    {
        return <<<TEXT
            Bonjour $name,

            Tu as demandé à changer ton mot de passe QuizLab. Ouvre ce lien pour en choisir un nouveau :

            $link

            Le lien est valable $validity heure et ne fonctionne qu'une fois.

            Si tu n'as rien demandé, ignore ce message : ton mot de passe reste inchangé.

            — QuizLab
            TEXT;
    }
}
