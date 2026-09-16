<?php

namespace App\Identity\Application;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\ApiTokenRepository;
use App\Identity\Domain\Repository\UserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Vérifie les identifiants et ouvre une session (jeton). */
class LoginUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly ApiTokenRepository $tokens,
    ) {
    }

    /** @return array{user: User, token: string}|null null si l'e-mail ou le mot de passe est incorrect */
    public function login(string $email, #[\SensitiveParameter] string $password): ?array
    {
        $user = $this->users->findOneByEmail($email);

        if (!$user) {
            // Hachage pour rien, mais qui prend autant de temps que la vérification d'un vrai mot de
            // passe : sinon, la rapidité de la réponse révélerait quelles adresses ont un compte.
            $this->hasher->hashPassword(new User(), $password);

            return null;
        }

        if (!$this->hasher->isPasswordValid($user, $password)) {
            return null;
        }

        if ($this->hasher->needsRehash($user)) {
            $user->setPassword($this->hasher->hashPassword($user, $password));
        }

        $user->touch();

        // L'émission du jeton enregistre aussi le rehachage et la date de dernière visite.
        return ['user' => $user, 'token' => $this->tokens->issue($user)];
    }
}
