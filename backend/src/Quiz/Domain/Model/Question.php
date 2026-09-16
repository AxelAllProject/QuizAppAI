<?php

namespace App\Quiz\Domain\Model;

use Doctrine\ORM\Mapping as ORM;

/** Question d'un quiz : intitulé, propositions, bonne réponse, chrono et illustration. */
#[ORM\Entity]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quiz::class, inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Quiz $quiz = null;

    #[ORM\Column(type: 'text')]
    private string $text = '';

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $choices = [];

    #[ORM\Column]
    private int $correctIndex = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $explanation = null;

    #[ORM\Column]
    private int $position = 0;

    /** Chemin public de l'illustration (/uploads/…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Chrono de la question en partie en direct, en secondes. */
    #[ORM\Column(options: ['default' => 20])]
    private int $timeLimit = 20;

    // Accesseurs : lecture et modification des champs de l'entité.
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

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /** @return list<string> */
    public function getChoices(): array
    {
        return $this->choices;
    }

    /** @param list<string> $choices */
    public function setChoices(array $choices): self
    {
        $this->choices = array_values($choices);

        return $this;
    }

    public function getCorrectIndex(): int
    {
        return $this->correctIndex;
    }

    public function setCorrectIndex(int $correctIndex): self
    {
        $this->correctIndex = $correctIndex;

        return $this;
    }

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): self
    {
        $this->explanation = $explanation;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getTimeLimit(): int
    {
        return $this->timeLimit;
    }

    public function setTimeLimit(int $timeLimit): self
    {
        $this->timeLimit = $timeLimit;

        return $this;
    }
}
