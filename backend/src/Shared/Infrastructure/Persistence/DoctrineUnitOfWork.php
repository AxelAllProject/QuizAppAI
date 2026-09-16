<?php

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Application\UnitOfWork;
use App\Shared\Domain\Exception\DuplicateEntryException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(UnitOfWork::class)]
class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function flush(): void
    {
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $exception) {
            throw new DuplicateEntryException($exception->getMessage(), previous: $exception);
        }
    }

    public function transactional(callable $operation): mixed
    {
        return $this->em->wrapInTransaction($operation);
    }
}
