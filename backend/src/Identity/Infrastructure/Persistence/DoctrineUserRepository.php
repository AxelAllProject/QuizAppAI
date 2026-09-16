<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<User> */
#[AsAlias(UserRepository::class)]
class DoctrineUserRepository extends ServiceEntityRepository implements UserRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function ofId(int $id): ?User
    {
        return $this->find($id);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function hasAdmin(): bool
    {
        return $this->count(['role' => User::ROLE_ADMIN]) > 0;
    }

    /** « Axel » et « axel » ne peuvent pas coexister : on s'y tromperait dans un classement. */
    public function isUsernameTaken(string $username): bool
    {
        return (bool) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('LOWER(u.username) = :name')
            ->setParameter('name', mb_strtolower(trim($username)))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Annuaire du back-office : recherche par pseudo et filtre par rôle, faits en base.
     *
     * @return User[]
     */
    public function search(?string $term = null, ?string $role = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.lastSeenAt', 'DESC')
            // Départage les comptes vus à la même seconde : sans ça l'ordre du listing varie.
            ->addOrderBy('u.id', 'DESC');

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(u.username) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== $role) {
            $qb->andWhere('u.role = :role')->setParameter('role', $role);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return User[] */
    public function findInactiveSince(\DateTimeImmutable $limit): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastSeenAt < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->getResult();
    }

    public function add(User $user): void
    {
        $this->getEntityManager()->persist($user);
    }

    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
    }
}
