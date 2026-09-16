<?php

namespace App\Identity\Application;

use App\Access\Application\AccessKeyRedeemer;
use App\Identity\Application\Exception\RegistrationFailedException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\Exception\DuplicateEntryException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Inscription : e-mail, pseudo, mot de passe et consentement. Une clé d'accès donne en plus un rôle prof ou admin. */
class RegisterUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AccessKeyRedeemer $redeemer,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /** @throws RegistrationFailedException */
    public function register(string $email, string $name, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $accessKey): User
    {
        $errors = [];

        if ($this->users->findOneByEmail($email)) {
            $errors[] = 'Un compte existe déjà avec cette adresse e-mail.';
        }

        if ($this->users->isUsernameTaken($name)) {
            $errors[] = 'Ce pseudo est déjà pris.';
        }

        if ([] !== $errors) {
            throw new RegistrationFailedException($errors);
        }

        $user = (new User())
            ->setEmail($email)
            ->setUsername($name)
            ->acceptPrivacyPolicy();

        try {
            // Transaction : si l'enregistrement échoue, la réservation d'une clé admin est annulée avec lui.
            $registered = $this->unitOfWork->transactional(function () use ($user, $password, $accessKey): bool {
                // La clé n'est consommée qu'une fois le reste du formulaire valide. Une clé refusée
                // n'a rien écrit : on sort sans exception, qui fermerait l'EntityManager.
                if ('' !== trim((string) $accessKey) && !$this->redeemer->redeem((string) $accessKey, $user)) {
                    return false;
                }

                $user->setPassword($this->hasher->hashPassword($user, $password));
                $this->users->add($user);
                $this->unitOfWork->flush();

                return true;
            });
        } catch (DuplicateEntryException) {
            // Deux inscriptions simultanées avec la même adresse ou le même pseudo : la base n'en garde qu'une.
            throw new RegistrationFailedException(['Un compte existe déjà avec cette adresse e-mail ou ce pseudo.']);
        }

        if (!$registered) {
            throw new RegistrationFailedException(["Clé d'accès invalide ou révoquée."]);
        }

        return $user;
    }
}
