<?php

namespace App\Identity\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Compte utilisateur. Données limitées au strict nécessaire (RGPD, minimisation) :
 * un e-mail pour se connecter, un pseudo affiché, le mot de passe haché
 * et la trace du consentement à la politique de confidentialité.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
// Annuaire trié par dernière connexion, purge des comptes inactifs.
#[ORM\Index(fields: ['lastSeenAt'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLE_PLAYER = 'user';
    public const ROLE_TEACHER = 'prof';
    public const ROLE_ADMIN = 'admin';
    public const ROLES = [self::ROLE_PLAYER, self::ROLE_TEACHER, self::ROLE_ADMIN];

    /** Version de la politique de confidentialité acceptée à l'inscription. */
    public const PRIVACY_POLICY_VERSION = '2026-09-16';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 60, unique: true)]
    private string $username = '';

    /**
     * Pseudo en minuscules, unique en base : « Axel » et « axel » ne peuvent pas coexister,
     * même avec deux inscriptions simultanées (l'index unique sur `username` distingue la casse).
     */
    #[ORM\Column(length: 60, unique: true)]
    private string $usernameCanonical = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_PLAYER;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastSeenAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $consentedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $consentVersion = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSeenAt = $this->createdAt;
    }

    // Accesseurs : lecture et modification des champs de l'entité.
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = trim($username);
        $this->usernameCanonical = self::canonicalize($username);

        return $this;
    }

    /** Forme de comparaison d'un pseudo : sans espaces autour, en minuscules (accents compris). */
    public static function canonicalize(string $username): string
    {
        return mb_strtolower(trim($username));
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): self
    {
        $this->password = $hashedPassword;

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    /** Une clé d'accès ne fait que monter en grade : un admin qui saisit une clé prof reste admin. */
    public function promote(string $role): self
    {
        if (array_search($role, self::ROLES, true) > array_search($this->role, self::ROLES, true)) {
            $this->role = $role;
        }

        return $this;
    }

    public function getRoles(): array
    {
        return match ($this->role) {
            self::ROLE_ADMIN => ['ROLE_USER', 'ROLE_ADMIN'],
            self::ROLE_TEACHER => ['ROLE_USER', 'ROLE_TEACHER'],
            default => ['ROLE_USER'],
        };
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    /** Note la date de dernière visite (sert à la purge des comptes inactifs). */
    public function touch(): self
    {
        $this->lastSeenAt = new \DateTimeImmutable();

        return $this;
    }

    public function getConsentedAt(): ?\DateTimeImmutable
    {
        return $this->consentedAt;
    }

    public function getConsentVersion(): ?string
    {
        return $this->consentVersion;
    }

    /** Enregistre la date et la version de la politique de confidentialité acceptée. */
    public function acceptPrivacyPolicy(string $version = self::PRIVACY_POLICY_VERSION): self
    {
        $this->consentedAt = new \DateTimeImmutable();
        $this->consentVersion = $version;

        return $this;
    }
}
