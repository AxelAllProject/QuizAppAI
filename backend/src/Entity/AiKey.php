<?php

namespace App\Entity;

use App\Repository\AiKeyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Clé donnant droit à un nombre fixe de générations de quiz par IA — une fonctionnalité
 * premium distincte du rôle professeur/admin : avoir le rôle ne suffit pas, il faut en
 * plus détenir une clé IA active. Émise par un administrateur, liée au premier compte
 * qui la saisit, avec un total qui ne se renouvelle pas et une expiration facultative.
 */
#[ORM\Entity(repositoryClass: AiKeyRepository::class)]
class AiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $value;

    #[ORM\Column]
    private int $totalGenerations;

    #[ORM\Column]
    private int $remainingGenerations;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 60)]
    private string $createdBy = 'système';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $redeemedBy = null;

    /** Copie du pseudo au moment de la saisie : reste lisible même si le compte est supprimé. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $redeemedByName = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $redeemedAt = null;

    public function __construct(string $value, int $totalGenerations)
    {
        $this->value = $value;
        $this->totalGenerations = $totalGenerations;
        $this->remainingGenerations = $totalGenerations;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getTotalGenerations(): int
    {
        return $this->totalGenerations;
    }

    public function getRemainingGenerations(): int
    {
        return $this->remainingGenerations;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCreatedBy(): string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(string $createdBy): self
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): self
    {
        $this->revokedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= $now;
    }

    public function isExhausted(): bool
    {
        return $this->remainingGenerations <= 0;
    }

    public function getRedeemedBy(): ?User
    {
        return $this->redeemedBy;
    }

    public function getRedeemedByName(): ?string
    {
        return $this->redeemedByName;
    }

    public function getRedeemedAt(): ?\DateTimeImmutable
    {
        return $this->redeemedAt;
    }

    public function isRedeemedBySomeoneElse(User $user): bool
    {
        return null !== $this->redeemedBy && $this->redeemedBy->getId() !== $user->getId();
    }

    /** Lie la clé au premier compte qui la saisit ; sans effet si déjà liée à ce même compte. */
    public function redeemFor(User $user): self
    {
        if (null === $this->redeemedBy) {
            $this->redeemedBy = $user;
            $this->redeemedByName = $user->getUsername();
            $this->redeemedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function consume(): self
    {
        if ($this->remainingGenerations > 0) {
            --$this->remainingGenerations;
        }

        return $this;
    }
}
