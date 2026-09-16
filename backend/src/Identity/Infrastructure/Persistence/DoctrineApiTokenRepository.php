<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\ApiToken;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\ApiTokenRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<ApiToken> */
#[AsAlias(ApiTokenRepository::class)]
class DoctrineApiTokenRepository extends ServiceEntityRepository implements ApiTokenRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
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
            ->setParameter('now', $this->clock->now())
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
            ->setParameter('now', $this->clock->now())
            ->getQuery()
            ->execute();
    }

    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    private static function hash(#[\SensitiveParameter] string $plain): string
    {
        return hash('sha256', $plain);
    }
}
