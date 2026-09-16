<?php

namespace App\Identity\Infrastructure\DataFixtures;

use App\Identity\Domain\Model\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/** Comptes de démonstration : un par rôle au minimum, tous avec le même mot de passe. */
class UserFixtures extends Fixture implements FixtureGroupInterface
{
    /** Identifiant de développement local uniquement, documenté dans docs/fixtures.md. */
    public const PASSWORD = 'quizlab-demo';

    /** pseudo => rôle */
    public const ACCOUNTS = [
        'direction' => User::ROLE_ADMIN,
        'mme.martin' => User::ROLE_TEACHER,
        'm.durand' => User::ROLE_TEACHER,
        'lea' => User::ROLE_PLAYER,
        'hugo' => User::ROLE_PLAYER,
        'nina' => User::ROLE_PLAYER,
        'tom' => User::ROLE_PLAYER,
        'sarah' => User::ROLE_PLAYER,
    ];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    /** Nom de référence d'un compte, pour le retrouver dans les autres fixtures. */
    public static function ref(string $username): string
    {
        return 'user.'.$username;
    }

    /** Adresse e-mail d'un compte de démonstration. */
    public static function email(string $username): string
    {
        return $username.'@quizlab.test';
    }

    /** Crée les comptes de démonstration avec leur rôle. */
    public function load(ObjectManager $manager): void
    {
        foreach (self::ACCOUNTS as $username => $role) {
            $user = (new User())
                ->setEmail(self::email($username))
                ->setUsername($username)
                ->setRole($role)
                ->acceptPrivacyPolicy();
            $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));

            $manager->persist($user);
            $this->addReference(self::ref($username), $user);
        }

        $manager->flush();
    }

    /** Groupes permettant de charger ces fixtures seules (--group=accounts). */
    public static function getGroups(): array
    {
        return ['demo', 'accounts'];
    }
}
