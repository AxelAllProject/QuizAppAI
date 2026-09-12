<?php

namespace App\Repository;

use App\Entity\AiKey;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;

/** @extends ServiceEntityRepository<AiKey> */
class AiKeyRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, AiKey::class);
    }

    public function findByValue(string $value): ?AiKey
    {
        return $this->findOneBy(['value' => $value]);
    }

    /** La clé la plus récemment liée à ce compte qui a encore des générations disponibles. */
    public function findActiveFor(User $user): ?AiKey
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.redeemedBy = :user')
            ->andWhere('k.revokedAt IS NULL')
            ->andWhere('k.remainingGenerations > 0')
            ->andWhere('k.expiresAt IS NULL OR k.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $this->clock->now())
            ->orderBy('k.redeemedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return AiKey[] */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->orderBy('k.revokedAt', 'ASC')
            ->addOrderBy('k.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
