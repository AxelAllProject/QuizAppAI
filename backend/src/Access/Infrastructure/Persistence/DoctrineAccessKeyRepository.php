<?php

namespace App\Access\Infrastructure\Persistence;

use App\Access\Domain\Model\AccessKey;
use App\Access\Domain\Repository\AccessKeyRepository;
use App\Identity\Domain\Model\User;
use App\Shared\Infrastructure\Persistence\LikePattern;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** @extends ServiceEntityRepository<AccessKey> */
#[AsAlias(AccessKeyRepository::class)]
class DoctrineAccessKeyRepository extends ServiceEntityRepository implements AccessKeyRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($registry, AccessKey::class);
    }

    /** Récupère une clé par son identifiant. */
    public function ofId(int $id): ?AccessKey
    {
        return $this->find($id);
    }

    /** Récupère une clé par son code. */
    public function ofValue(string $value): ?AccessKey
    {
        return $this->findOneBy(['value' => $value]);
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
            $qb->andWhere(sprintf('LOWER(k.value) LIKE :term %1$s OR LOWER(k.label) LIKE :term %1$s OR LOWER(k.assignedToName) LIKE :term %1$s', LikePattern::ESCAPE))
                ->setParameter('term', LikePattern::contains($term));
        }

        if (null !== $role) {
            $qb->andWhere('k.role = :role')->setParameter('role', $role);
        }

        $this->applyStatus($qb, $status);

        return $qb->getQuery()->getResult();
    }

    /** Ajoute à la requête le filtre correspondant à l'état demandé (active, attribuée, périmée, révoquée). */
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

    /** Remplace le pseudo du compte supprimé sur les clés qu'il a créées, et le détache des clés qui lui ont été attribuées. */
    public function anonymizeUser(User $user, string $anonymous): void
    {
        $this->createQueryBuilder('k')
            ->update()
            ->set('k.createdBy', ':anonymous')
            ->andWhere('k.createdBy = :name')
            ->setParameter('anonymous', $anonymous)
            ->setParameter('name', $user->getUsername())
            ->getQuery()
            ->execute();

        $this->createQueryBuilder('k')
            ->update()
            ->set('k.assignedTo', ':none')
            ->set('k.assignedToName', ':anonymous')
            ->andWhere('k.assignedTo = :user')
            ->setParameter('none', null)
            ->setParameter('anonymous', $anonymous)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /** Prépare l'enregistrement d'une nouvelle clé. */
    public function add(AccessKey $accessKey): void
    {
        $this->getEntityManager()->persist($accessKey);
    }
}
