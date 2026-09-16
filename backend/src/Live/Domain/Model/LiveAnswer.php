<?php

namespace App\Live\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/** Réponse d'un joueur à une question d'une partie en direct. */
#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['player_id', 'question_index'])]
class LiveAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: LivePlayer::class, inversedBy: 'answers')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private LivePlayer $player,

        #[ORM\Column]
        private int $questionIndex,

        #[ORM\Column]
        private int $choiceIndex,

        #[ORM\Column]
        private bool $correct,

        #[ORM\Column]
        private int $points,

        /** Temps de réponse depuis l'affichage de la question. */
        #[ORM\Column]
        private int $elapsedMs,
    ) {
    }

    // Accesseurs : lecture et modification des champs de l'entité.
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlayer(): LivePlayer
    {
        return $this->player;
    }

    public function getQuestionIndex(): int
    {
        return $this->questionIndex;
    }

    public function getChoiceIndex(): int
    {
        return $this->choiceIndex;
    }

    /** Indique si le joueur a choisi la bonne réponse. */
    public function isCorrect(): bool
    {
        return $this->correct;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function getElapsedMs(): int
    {
        return $this->elapsedMs;
    }
}
