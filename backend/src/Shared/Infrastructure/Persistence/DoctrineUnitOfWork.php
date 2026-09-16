<?php

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\Exception\ConcurrentModificationException;
use App\Shared\Domain\Exception\DuplicateEntryException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** Implémentation Doctrine de l'unité de travail. */
#[AsAlias(UnitOfWork::class)]
class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** Écrit en base les changements en attente, et traduit les conflits d'écriture en exceptions métier. */
    public function flush(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateEntryException($exception->getMessage(), previous: $exception);
        } catch (OptimisticLockException $exception) {
            throw new ConcurrentModificationException($exception->getMessage(), previous: $exception);
        }
    }

    /** Exécute l'opération dans une transaction Doctrine. */
    public function transactional(callable $operation): mixed
    {
        return $this->em->wrapInTransaction($operation);
    }
}
