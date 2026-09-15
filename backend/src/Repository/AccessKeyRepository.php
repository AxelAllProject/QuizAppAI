<?php

namespace App\Repository;

use App\Entity\AccessKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;

/** @extends ServiceEntityRepository<AccessKey> */
class AccessKeyRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, AccessKey::class);
    }

    /** Une clé révoquée — ou périmée — ne donne plus aucun droit. */
    public function findActive(string $value): ?AccessKey
    {
        return $this->createQueryBuilder('k')
            ->andWhere('k.value = :value')
            ->andWhere('k.revokedAt IS NULL')
            ->andWhere('k.expiresAt IS NULL OR k.expiresAt > :now')
            ->setParameter('value', $value)
            ->setParameter('now', $this->clock->now())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Réserve une clé à usage unique. La mise à jour ne passe que si la clé n'est pas encore
     * close : la base le garantit même pour deux requêtes simultanées, un simple test en PHP non.
     */
    public function claim(AccessKey $key): bool
    {
        return 1 === $this->createQueryBuilder('k')
            ->update()
            ->set('k.revokedAt', ':now')
            ->andWhere('k.id = :id')
            ->andWhere('k.revokedAt IS NULL')
            ->setParameter('now', $this->clock->now())
            ->setParameter('id', $key->getId())
            ->getQuery()
            ->execute();
    }

    /** @return AccessKey[] */
    public function findAllOrdered(): array
    {
        return $this->search();
    }

    /**
     * Listing du back-office : recherche libre (code, étiquette, bénéficiaire) et
     * filtres rôle / état. L'état se calcule en base pour que la pagination reste juste.
     *
     * @return AccessKey[]
     */
    public function search(?string $term = null, ?string $role = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('k')
            ->orderBy('k.createdAt', 'DESC')
            // Départage les clés créées à la même seconde : sans ça l'ordre du listing varie.
            ->addOrderBy('k.id', 'DESC');

        if (null !== $term && '' !== trim($term)) {
            $qb->andWhere('LOWER(k.value) LIKE :term OR LOWER(k.label) LIKE :term OR LOWER(k.assignedToName) LIKE :term')
                ->setParameter('term', '%'.mb_strtolower(trim($term)).'%');
        }

        if (null !== $role) {
            $qb->andWhere('k.role = :role')->setParameter('role', $role);
        }

        $this->applyStatus($qb, $status);

        return $qb->getQuery()->getResult();
    }

    private function applyStatus(QueryBuilder $qb, ?string $status): void
    {
        if (null === $status) {
            return;
        }

        $assigned = 'k.assignedToName IS NOT NULL';
        $now = $this->clock->now();

        match ($status) {
            AccessKey::STATUS_ASSIGNED => $qb->andWhere($assigned),
            AccessKey::STATUS_REVOKED => $qb->andWhere(sprintf('k.revokedAt IS NOT NULL AND NOT (%s)', $assigned)),
            AccessKey::STATUS_EXPIRED => $qb
                ->andWhere(sprintf('k.revokedAt IS NULL AND NOT (%s)', $assigned))
                ->andWhere('k.expiresAt IS NOT NULL AND k.expiresAt <= :now')
                ->setParameter('now', $now),
            AccessKey::STATUS_ACTIVE => $qb
                ->andWhere(sprintf('k.revokedAt IS NULL AND NOT (%s)', $assigned))
                ->andWhere('k.expiresAt IS NULL OR k.expiresAt > :now')
                ->setParameter('now', $now),
            default => null,
        };
    }
}
