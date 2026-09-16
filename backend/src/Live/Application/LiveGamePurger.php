<?php

namespace App\Live\Application;

use App\Live\Domain\Repository\LiveGameRepository;
use App\Shared\Application\UnitOfWork;

/** Supprime les parties en direct anciennes, avec leurs joueurs et leurs réponses. */
class LiveGamePurger
{
    public function __construct(
        private readonly LiveGameRepository $games,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function countCreatedBefore(\DateTimeImmutable $limit): int
    {
        return count($this->games->findCreatedBefore($limit));
    }

    public function purgeCreatedBefore(\DateTimeImmutable $limit): int
    {
        $games = $this->games->findCreatedBefore($limit);
        $this->games->removeAll($games);
        $this->unitOfWork->flush();

        return count($games);
    }
}
