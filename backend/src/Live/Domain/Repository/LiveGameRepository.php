<?php

namespace App\Live\Domain\Repository;

use App\Identity\Domain\Model\User;
use App\Live\Domain\Model\LiveGame;
use App\Live\Domain\Model\LivePlayer;
use App\Quiz\Domain\Model\Quiz;

/** Accès aux parties en direct (implémenté avec Doctrine dans Infrastructure). */
interface LiveGameRepository
{
    /** Un code PIN finit par resservir : on prend la partie la plus récente. */
    public function findLatestByPin(string $pin): ?LiveGame;

    /** Indique si le code PIN est déjà pris par une partie non terminée. */
    public function isPinInUse(string $pin): bool;

    /** @return LiveGame[] */
    public function findCreatedBefore(\DateTimeImmutable $limit): array;

    /** @return LiveGame[] */
    public function findByHost(User $host): array;

    /** @return LivePlayer[] */
    public function findParticipations(User $user): array;

    /** Prépare l'enregistrement d'une nouvelle partie (écrite au prochain flush). */
    public function add(LiveGame $game): void;

    /**
     * Supprime les parties avec leurs joueurs et leurs réponses.
     *
     * @param iterable<LiveGame> $games
     */
    public function removeAll(iterable $games): void;

    /** Supprime toutes les parties jouées sur un quiz. */
    public function removeForQuiz(Quiz $quiz): void;

    /** Retire un joueur de sa partie, avec ses réponses. */
    public function removePlayer(LivePlayer $player): void;
}
