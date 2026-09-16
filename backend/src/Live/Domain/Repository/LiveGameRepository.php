<?php

namespace App\Live\Domain\Repository;

use App\Identity\Domain\Model\User;
use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Model\LivePlayer;
use App\Quiz\Domain\Model\Quiz;

interface LiveGameRepository
{
    /** Un code PIN finit par resservir : on prend la partie la plus récente. */
    public function findLatestByPin(string $pin): ?LiveGame;

    public function isPinInUse(string $pin): bool;

    /** @return LiveGame[] */
    public function findCreatedBefore(\DateTimeImmutable $limit): array;

    /** @return LiveGame[] */
    public function findByHost(User $host): array;

    /** @return LivePlayer[] */
    public function findParticipations(User $user): array;

    public function add(LiveGame $game): void;

    /**
     * Supprime les parties avec leurs joueurs et leurs réponses.
     *
     * @param iterable<LiveGame> $games
     */
    public function removeAll(iterable $games): void;

    public function removeForQuiz(Quiz $quiz): void;

    /** Retire un joueur de sa partie, avec ses réponses. */
    public function removePlayer(LivePlayer $player): void;
}
