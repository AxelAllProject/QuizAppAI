<?php

namespace App\Entity;

use App\Repository\GameSessionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GameSessionRepository::class)]
class GameSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quiz $quiz = null;

    /** Copie du titre : la session reste lisible même si le quiz est supprimé. */
    #[ORM\Column(length: 180)]
    private string $quizTitle = '';

    #[ORM\Column(length: 60)]
    private string $player = 'anonyme';

    /** Compte du joueur : ses parties sont effacées avec lui. */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column]
    private int $total = 0;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    /** @var list<array<string, mixed>> Détail question par question, figé au moment de la partie. */
    #[ORM\Column(type: 'json')]
    private array $answers = [];

    #[ORM\Column]
    private \DateTimeImmutable $playedAt;

    public function __construct()
    {
        $this->playedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): self
    {
        $this->quiz = $quiz;

        return $this;
    }

    public function getQuizTitle(): string
    {
        return $this->quizTitle;
    }

    public function setQuizTitle(string $quizTitle): self
    {
        $this->quizTitle = $quizTitle;

        return $this;
    }

    public function getPlayer(): string
    {
        return $this->player;
    }

    public function setPlayer(string $player): self
    {
        $this->player = $player;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): self
    {
        $this->total = $total;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /** @param list<array<string, mixed>> $answers */
    public function setAnswers(array $answers): self
    {
        $this->answers = $answers;

        return $this;
    }

    public function getPlayedAt(): \DateTimeImmutable
    {
        return $this->playedAt;
    }
}
