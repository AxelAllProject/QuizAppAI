<?php

namespace App\Identity\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton de réinitialisation de mot de passe, envoyé par e-mail. Comme pour ApiToken,
 * seule son empreinte SHA-256 est stockée : le lien reçu par la personne est la seule
 * copie en clair, et une fuite de la base ne permet pas de reprendre un compte.
 *
 * Durée volontairement courte, et usage unique : un lien qui traîne dans une boîte
 * aux lettres partagée ne doit pas rester une porte ouverte.
 */
#[ORM\Entity]
// Purge des jetons expirés.
#[ORM\Index(fields: ['expiresAt'])]
class PasswordResetToken
{
    /** Au-delà, le lien ne vaut plus rien et il faut en redemander un. */
    public const LIFETIME = '+1 hour';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify(self::LIFETIME);
    }

    // Accesseurs : lecture et modification des champs de l'entité.
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?\DateTimeImmutable
    {
        return $this->usedAt;
    }

    /** Un lien ne sert qu'une fois : la date d'usage le referme définitivement. */
    public function isUsable(\DateTimeImmutable $now): bool
    {
        return null === $this->usedAt && $this->expiresAt > $now;
    }

    /** Marque le lien comme consommé (la première date d'usage est conservée). */
    public function markUsed(): self
    {
        $this->usedAt ??= new \DateTimeImmutable();

        return $this;
    }
}
