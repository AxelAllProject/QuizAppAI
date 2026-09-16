<?php

namespace App\Quiz\Domain\Repository;

use App\Identity\Domain\Model\User;
use App\Quiz\Domain\Model\Quiz;

interface QuizRepository
{
    public function ofId(int $id): ?Quiz;

    public function findOneByTitle(string $title): ?Quiz;

    /** @return Quiz[] */
    public function findByOwner(User $owner): array;

    /** @return Quiz[] */
    public function search(?string $term, ?string $category, ?User $owner): array;

    /** @return string[] */
    public function findCategories(): array;

    public function countAll(): int;

    /** Les quiz d'un compte supprimé restent jouables, sans propriétaire et sous un pseudo anonyme. */
    public function anonymizeOwner(User $owner, string $anonymous): void;

    public function add(Quiz $quiz): void;

    public function remove(Quiz $quiz): void;
}
