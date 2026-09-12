<?php

namespace App\Entity;

use App\Repository\AccessKeyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Clé d'accès distribuée par un administrateur. Elle ne représente pas un compte :
 * elle donne un rôle (professeur ou administrateur) à qui la saisit à la connexion.
 */
#[ORM\Entity(repositoryClass: AccessKeyRepository::class)]
class AccessKey
{
    public const ROLE_TEACHER = 'prof';
    public const ROLE_ADMIN = 'admin';
    public const ROLES = [self::ROLE_TEACHER, self::ROLE_ADMIN];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $value = '';

    #[ORM\Column(length: 20)]
    private string $role = self::ROLE_TEACHER;

    /** Étiquette libre, pour savoir à qui la clé a été remise. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 60)]
    private string $createdBy = 'système';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    /** Copie du pseudo au moment de l'attribution : reste lisible même si le compte est supprimé. */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $assignedToName = null;

    public function __construct()
    {
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

    public function setValue(string $value): self
    {
        $this->value = $value;

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

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isActive(): bool
    {
        return null === $this->revokedAt;
    }

    public function revoke(): self
    {
        $this->revokedAt ??= new \DateTimeImmutable();

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function markUsed(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        ++$this->usageCount;

        return $this;
    }

    public function getAssignedTo(): ?User
    {
        return $this->assignedTo;
    }

    public function getAssignedToName(): ?string
    {
        return $this->assignedToName;
    }

    /**
     * Attribution directe à un compte choisi par l'administrateur (plutôt qu'un code partagé
     * saisi par la personne elle-même) : le rôle est accordé tout de suite, et la clé est
     * close derrière — elle a rempli son rôle, elle ne doit plus pouvoir resservir à quelqu'un d'autre.
     */
    public function assignTo(User $user): self
    {
        $user->promote($this->role);
        $this->assignedTo = $user;
        $this->assignedToName = $user->getUsername();
        $this->markUsed();
        $this->revoke();

        return $this;
    }
}
