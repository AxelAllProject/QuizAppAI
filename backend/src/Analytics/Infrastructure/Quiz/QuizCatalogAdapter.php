<?php

namespace App\Analytics\Infrastructure\Quiz;

use App\Analytics\Application\QuizCatalog;
use App\Quiz\Domain\Repository\QuizRepository;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** Adaptateur : va chercher dans le contexte Quiz de quoi habiller le palmarès. */
#[AsAlias(QuizCatalog::class)]
class QuizCatalogAdapter implements QuizCatalog
{
    public function __construct(private readonly QuizRepository $quizzes)
    {
    }

    /**
     * Le palmarès tient en dix lignes : dix lectures par identifiant plutôt qu'une requête
     * dédiée dans le contexte Quiz, qui n'a pas à gagner une méthode pour si peu.
     *
     * @param list<int> $ids
     *
     * @return array<int, array{title: string, category: string}>
     */
    public function describe(array $ids): array
    {
        $described = [];

        foreach ($ids as $id) {
            if ($quiz = $this->quizzes->ofId($id)) {
                $described[$id] = ['title' => $quiz->getTitle(), 'category' => $quiz->getCategory()];
            }
        }

        return $described;
    }

    /** Nombre total de quiz publiés. */
    public function countAll(): int
    {
        return $this->quizzes->countAll();
    }
}
