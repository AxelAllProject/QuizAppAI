<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\PasswordResetToken;
use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\PasswordResetTokenRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<PasswordResetToken> */
#[AsAlias(PasswordResetTokenRepository::class)]
class DoctrinePasswordResetTokenRepository extends ServiceEntityRepository implements PasswordResetTokenRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, PasswordResetToken::class);
    }

    /**
     * Crée un jeton et renvoie sa valeur en clair. Les demandes précédentes du même compte
     * sont effacées : redemander un lien invalide le précédent, comme on l'attend.
     */
    public function issue(User $user): string
    {
        $this->deleteByUser($user);
        $plain = bin2hex(random_bytes(32));

        $this->getEntityManager()->persist(new PasswordResetToken($user, self::hash($plain)));
        $this->getEntityManager()->flush();

        return $plain;
    }

    /** Récupère un jeton encore valable par l'empreinte de sa valeur, avec son compte. */
    public function findUsable(#[\SensitiveParameter] string $plain): ?PasswordResetToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->andWhere('t.tokenHash = :hash')
            ->andWhere('t.usedAt IS NULL')
            ->andWhere('t.expiresAt > :now')
            ->setParameter('hash', self::hash($plain))
            ->setParameter('now', $this->clock->now())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Supprime les jetons périmés ou consommés, en une requête. */
    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.expiresAt <= :now OR t.usedAt IS NOT NULL')
            ->setParameter('now', $this->clock->now())
            ->getQuery()
            ->execute();
    }

    /** Supprime tous les jetons d'un compte, en une requête. */
    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /** Empreinte SHA-256 du jeton : seule elle est stockée en base. */
    private static function hash(#[\SensitiveParameter] string $plain): string
    {
        return hash('sha256', $plain);
    }
}
