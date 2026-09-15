<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<User> */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
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

    /** @return User[] */
    public function findAllOrdered(): array
    {
        return $this->search();
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
}
