<?php

namespace App\Identity\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton de connexion remis au navigateur. Seule son empreinte SHA-256 est
 * stockée : une fuite de la base ne permet pas de se faire passer pour quelqu'un.
 */
#[ORM\Entity]
// Purge des jetons expirés.
#[ORM\Index(fields: ['expiresAt'])]
class ApiToken
{
    /** Au-delà, il faut se reconnecter (durée de conservation limitée). */
    public const LIFETIME = '+30 days';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,

        #[ORM\Column(length: 64, unique: true)]
        private string $tokenHash,
    ) {
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable(self::LIFETIME);
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
}
