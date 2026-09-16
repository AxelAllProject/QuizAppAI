<?php

namespace App\Shared\Application;

use App\Shared\Domain\Exception\ConcurrentModificationException;
use App\Shared\Domain\Exception\DuplicateEntryException;

/**
 * Enregistre en une fois les changements faits sur les agrégats. Les couches Domain et
 * Application n'en savent pas plus sur la persistance : Doctrine reste dans Infrastructure.
 */
interface UnitOfWork
{
    /**
     * @throws DuplicateEntryException         si une contrainte d'unicité de la base refuse l'écriture
     * @throws ConcurrentModificationException si un agrégat versionné a été modifié entre-temps
     */
    public function flush(): void;

    /**
     * Exécute $operation dans une transaction, validée à la fin (flush compris) ou annulée en cas d'exception.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;
}
