<?php

namespace App\Live\Domain\Model;

use App\Identity\Domain\Model\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Participation d'un compte à une partie en direct : pseudo, score et réponses. */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['game_id', 'user_id'])]
class LivePlayer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Copie du pseudo au moment où le joueur rejoint la partie. */
    #[ORM\Column(length: 60)]
    private string $nickname;

    #[ORM\Column]
    private int $score = 0;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    /** @var Collection<int, LiveAnswer> indexées par numéro de question */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: LiveAnswer::class, cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'questionIndex')]
    private Collection $answers;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: LiveGame::class, inversedBy: 'players')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private LiveGame $game,

        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private User $user,
    ) {
        $this->nickname = $user->getUsername();
        $this->answers = new ArrayCollection();
        $this->joinedAt = new \DateTimeImmutable();
    }

    // Accesseurs : lecture et modification des champs de l'entité.
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): LiveGame
    {
        return $this->game;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    /** @return Collection<int, LiveAnswer> */
    public function getAnswers(): Collection
    {
        return $this->answers;
    }

    /** Ajoute une réponse du joueur. */
    public function addAnswer(LiveAnswer $answer): self
    {
        $this->answers->set($answer->getQuestionIndex(), $answer);
        $this->score += $answer->getPoints();

        return $this;
    }

    /** Récupère la réponse du joueur à une question, ou null s'il n'a pas répondu. */
    public function answerFor(int $questionIndex): ?LiveAnswer
    {
        return $this->answers->get($questionIndex);
    }

    public function getCorrectCount(): int
    {
        return $this->answers->filter(static fn (LiveAnswer $answer) => $answer->isCorrect())->count();
    }
}
