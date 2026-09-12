<?php

namespace App\Entity;

use App\Repository\LiveGameRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Partie en direct, façon Kahoot : un animateur projette les questions,
 * les joueurs rejoignent avec un code PIN et répondent depuis leur appareil.
 */
#[ORM\Entity(repositoryClass: LiveGameRepository::class)]
#[ORM\Index(columns: ['pin'])]
class LiveGame
{
    public const STATUS_LOBBY = 'lobby';
    public const STATUS_QUESTION = 'question';
    public const STATUS_REVEAL = 'reveal';
    public const STATUS_FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_LOBBY;

    /** Index de la question en cours, -1 tant que la partie n'a pas commencé. */
    #[ORM\Column]
    private int $currentIndex = -1;

    /** Horodatage en millisecondes : les points dépendent de la rapidité de réponse. */
    #[ORM\Column(type: 'bigint', nullable: true)]
    private int|string|null $questionStartedAtMs = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $finishedAt = null;

    /** @var Collection<int, LivePlayer> */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: LivePlayer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $players;

    public function __construct(
        #[ORM\Column(length: 6)]
        private string $pin,

        #[ORM\ManyToOne(targetEntity: Quiz::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Quiz $quiz,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $host,
    ) {
        $this->players = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPin(): string
    {
        return $this->pin;
    }

    public function getQuiz(): Quiz
    {
        return $this->quiz;
    }

    public function getHost(): User
    {
        return $this->host;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isFinished(): bool
    {
        return self::STATUS_FINISHED === $this->status;
    }

    public function getCurrentIndex(): int
    {
        return $this->currentIndex;
    }

    public function getQuestionStartedAtMs(): ?int
    {
        return null === $this->questionStartedAtMs ? null : (int) $this->questionStartedAtMs;
    }

    public function startQuestion(int $index, int $nowMs): self
    {
        $this->status = self::STATUS_QUESTION;
        $this->currentIndex = $index;
        $this->questionStartedAtMs = $nowMs;

        return $this;
    }

    public function reveal(): self
    {
        $this->status = self::STATUS_REVEAL;

        return $this;
    }

    public function finish(\DateTimeImmutable $at): self
    {
        $this->status = self::STATUS_FINISHED;
        $this->finishedAt = $at;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** @return Collection<int, LivePlayer> */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(LivePlayer $player): self
    {
        if (!$this->players->contains($player)) {
            $this->players->add($player);
        }

        return $this;
    }

    public function removePlayer(LivePlayer $player): self
    {
        $this->players->removeElement($player);

        return $this;
    }
}
