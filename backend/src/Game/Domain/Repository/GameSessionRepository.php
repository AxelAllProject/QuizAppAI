<?php

namespace App\Game\Domain\Repository;

use App\Game\Domain\Model\GameSession;
use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;

/** Accès aux parties solo enregistrées (implémenté avec Doctrine dans Infrastructure). */
interface GameSessionRepository
{
    /** Récupère une partie par son identifiant, ou null si elle n'existe pas. */
    public function ofId(int $id): ?GameSession;

    /**
     * Parties d'un compte, ou de tout le monde si $user est null.
     *
     * @return GameSession[]
     */
    public function findHistory(?User $user, ?int $limit = 50): array;

    /** Les parties d'un quiz supprimé restent dans l'historique, grâce au titre recopié. */
    public function detachQuiz(Quiz $quiz): void;

    /**
     * Classement d'un quiz : la première partie de chaque joueur, du meilleur score au moins bon.
     *
     * @return GameSession[]
     */
    public function findByQuiz(int $quizId, int $limit = 100): array;

    /**
     * Classement : meilleur pourcentage par joueur.
     *
     * @return list<array{player: string, games: int, score: int, total: int, accuracy: float|int}>
     */
    public function leaderboard(int $limit = 10): array;

    /**
     * Parties, bonnes réponses et questions par compte, agrégées en base.
     *
     * @param User[] $users
     *
     * @return array<int, array{games: int, score: int, total: int}> par identifiant de compte
     */
    public function statsByUser(array $users): array;

    /** @return array{sessionCount: int, playerCount: int, score: int, total: int} */
    public function globalStats(): array;

    /**
     * Bilan d'un joueur sur toutes ses parties (l'historique, lui, s'arrête aux plus récentes).
     *
     * @return array{sessionCount: int, averageAccuracy: int|null} moyenne des taux de réussite, null sans partie
     */
    public function summaryFor(User $user): array;

    /** Supprime toutes les parties d'un compte. */
    public function deleteByUser(User $user): void;

    /** Prépare l'enregistrement d'une nouvelle partie (écrite au prochain flush). */
    public function add(GameSession $session): void;
}
