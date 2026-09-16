<?php

namespace App\Quiz\Domain\Repository;

use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;

/** Accès aux quiz enregistrés (implémenté avec Doctrine dans Infrastructure). */
interface QuizRepository
{
    /** Récupère un quiz par son identifiant, ou null s'il n'existe pas. */
    public function ofId(int $id): ?Quiz;

    /** Récupère un quiz par son titre exact. */
    public function findOneByTitle(string $title): ?Quiz;

    /** @return Quiz[] */
    public function findByOwner(User $owner): array;

    /** @return Quiz[] */
    public function search(?string $term, ?string $category, ?User $owner): array;

    /**
     * Nombre de questions de chaque quiz, en une requête : la bibliothèque n'a pas besoin des questions elles-mêmes.
     *
     * @param Quiz[] $quizzes
     *
     * @return array<int, int> nombre de questions, par identifiant de quiz
     */
    public function countQuestions(array $quizzes): array;

    /** @return string[] */
    public function findCategories(): array;

    /** Nombre total de quiz. */
    public function countAll(): int;

    /** Les quiz d'un compte supprimé restent jouables, sans propriétaire et sous un pseudo anonyme. */
    public function anonymizeOwner(User $owner, string $anonymous): void;

    /** Prépare l'enregistrement d'un nouveau quiz (écrit au prochain flush). */
    public function add(Quiz $quiz): void;

    /** Prépare la suppression d'un quiz et de ses questions (effective au prochain flush). */
    public function remove(Quiz $quiz): void;
}
