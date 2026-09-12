<?php

namespace App\Repository;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ApiToken> */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /** Crée un jeton et renvoie sa valeur en clair : c'est la seule fois où elle existe. */
    public function issue(User $user): string
    {
        $plain = bin2hex(random_bytes(32));

        $this->getEntityManager()->persist(new ApiToken($user, self::hash($plain)));
        $this->getEntityManager()->flush();

        return $plain;
    }

    public function findValid(#[\SensitiveParameter] string $plain): ?ApiToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', self::hash($plain))
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function revoke(#[\SensitiveParameter] string $plain): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.tokenHash = :hash')
            ->setParameter('hash', self::hash($plain))
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    private static function hash(#[\SensitiveParameter] string $plain): string
    {
        return hash('sha256', $plain);
    }
}
