<?php

namespace App\Identity\Application;

use App\Identity\Application\Exception\ProfileUpdateFailedException;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\Exception\DuplicateEntryException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * « Mon compte » : changer son e-mail, son pseudo ou son mot de passe.
 *
 * Le mot de passe actuel est exigé à chaque fois. Un jeton volé ne doit pas suffire à
 * détourner l'adresse de connexion du compte, ce qui reviendrait à en prendre possession.
 */
class UpdateProfile
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    /**
     * Applique les champs fournis (les autres restent inchangés).
     *
     * @throws ProfileUpdateFailedException
     */
    public function update(User $user, ?string $email, ?string $name, #[\SensitiveParameter] ?string $newPassword): User
    {
        $errors = [];
        $email = null === $email ? null : mb_strtolower(trim($email));
        $name = null === $name ? null : trim($name);

        // Comparaison sur la forme normalisée : renvoyer son propre e-mail ne doit pas
        // déclencher « adresse déjà prise », ni corriger seulement la casse du pseudo.
        if (null !== $email && $email !== $user->getEmail() && $this->users->findOneByEmail($email)) {
            $errors[] = 'Un compte existe déjà avec cette adresse e-mail.';
        }

        if (null !== $name && User::canonicalize($name) !== User::canonicalize($user->getUsername()) && $this->users->isUsernameTaken($name)) {
            $errors[] = 'Ce pseudo est déjà pris.';
        }

        if ([] !== $errors) {
            throw new ProfileUpdateFailedException($errors);
        }

        if (null !== $email) {
            $user->setEmail($email);
        }

        if (null !== $name) {
            $user->setUsername($name);
        }

        if (null !== $newPassword) {
            $user->setPassword($this->hasher->hashPassword($user, $newPassword));
        }

        try {
            $this->unitOfWork->flush();
        } catch (DuplicateEntryException) {
            // Deux modifications simultanées vers la même adresse ou le même pseudo : la base n'en garde qu'une.
            throw new ProfileUpdateFailedException(['Cette adresse e-mail ou ce pseudo vient d’être pris.']);
        }

        return $user;
    }
}
