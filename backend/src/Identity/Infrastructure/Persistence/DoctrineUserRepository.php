<?php

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Model\User;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Infrastructure\Persistence\LikePattern;
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

    /** Récupère un compte par son identifiant. */
    public function ofId(int $id): ?User
    {
        return $this->find($id);
    }

    /** Récupère un compte par son adresse e-mail normalisée. */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    /** Indique s'il existe déjà au moins un administrateur. */
    public function hasAdmin(): bool
    {
        return $this->count(['role' => User::ROLE_ADMIN]) > 0;
    }

    /** « Axel » et « axel » ne peuvent pas coexister : on s'y tromperait dans un classement. */
    public function isUsernameTaken(string $username): bool
    {
        // Colonne dédiée plutôt que LOWER() en SQL : SQLite ne met en minuscules que l'ASCII (« É » resterait « É »).
        return $this->count(['usernameCanonical' => User::canonicalize($username)]) > 0;
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
            $qb->andWhere(sprintf('LOWER(u.username) LIKE :term %1$s', LikePattern::ESCAPE))
                ->setParameter('term', LikePattern::contains($term));
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

    /** Prépare l'enregistrement d'un nouveau compte. */
    public function add(User $user): void
    {
        $this->getEntityManager()->persist($user);
    }

    /** Prépare la suppression d'un compte. */
    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
    }
}
